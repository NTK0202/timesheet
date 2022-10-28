<?php

namespace App\Repositories;

use App\Models\LeaveQuotas;
use App\Models\MemberRequestQuota;
use App\Models\Request;
use App\Models\Worksheet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminRepository extends BaseRepository
{

    public function getModel()
    {
        return Request::class;
    }

    public function getRequest($request)
    {
        $perPage = $request->per_page ?? config('common.default_per_page');

        $getRequests = $this->model;

        if (trim((string) $request->start_date) !== "") {
            $getRequests = $getRequests
                ->where('request_for_date', '>=', $request->start_date ?? "");
        }

        if (trim((string) $request->end_date) !== "") {
            $getRequests = $getRequests
                ->where('request_for_date', '<=', $request->end_date ?? "");
        }

        if (trim((string) $request->order_request_for_date) !== "") {
            $order = $request->order_by_created_at;
        } else {
            $order = 'desc';
        }

        $getRequests = $getRequests
            ->with('member')
            ->with('divisionMember')
            ->whereIn('status', [0, 1])
            ->orderBy('created_at', $order)
            ->orderBy('id', $order)
            ->paginate($perPage, ['*'], 'page');

        foreach ($getRequests as $request) {
            $request->divisionMember = $request->member->division->first()->division_name;
        }

        return $getRequests;
    }

    public function approve($request, $id)
    {
        $requestConfirm = $this->model->findOrFail($id);
        $status = $request->status;
        $comment = trim($request->comment);
        $memberId = $requestConfirm->member_id;
        $date = $requestConfirm->request_for_date;
        $requestType = $requestConfirm->request_type;
        $note = config('common.approve');
        $errorCount = $requestConfirm->error_count;
        $month = date('Y-m', strtotime($date));

        $worksheet = Worksheet::where('member_id', $memberId)->where('work_date', $date)->first();

        $data = [
            'status' => $status,
            'admin_approved_comment' => $comment,
            'admin_approved_at' => now(),
            'admin_approved_status' => 1,
        ];

        $this->update($id, $data);

        if ($status == 2) {
            $worksheet->note = isset($worksheet->note) && strpos($worksheet->note, 'Approved') ? $worksheet->note . ',' . $note[$requestType] : $note[$requestType];
            $worksheet->save();
            if ($errorCount != 0) {
                $requestQuota = MemberRequestQuota::where('member_id', $memberId)
                    ->where('month', $month)
                    ->first();

                $requestLeaveQuota = LeaveQuotas::where('member_id', $memberId)
                    ->whereYear('year', $date)
                    ->first();

                if ($requestType == 2) {
                    $requestLeaveQuota->remain = $requestLeaveQuota->remain + 1;
                    $requestLeaveQuota->paid_leave = $requestLeaveQuota->paid_leave + 1;
                    $requestLeaveQuota->save();
                } elseif ($requestType == 3) {
                    $requestLeaveQuota->unpaid_leave = $requestLeaveQuota->unpaid_leave + 1;
                    $requestLeaveQuota->save();
                } else {
                    $requestQuota->remain = $requestQuota->remain + 1;
                    $requestQuota->save();
                }
            }
        } else {
            $worksheet->note = null;
            $worksheet->save();
        }

        if ($status == 2) {
            return response()->json([
                'message' => 'Approve request successfully'
            ], Response::HTTP_CREATED);
        } else {
            return response()->json([
                'message' => 'Reject request successfully !'
            ], Response::HTTP_CREATED);
        }
    }

    public function show($id)
    {
        $requests = $this->model
            ->where('id', $id)
            ->whereIn('status', [0, 1]);

        if ($requests->exists()) {
            $request = $requests->first();
            if ($request->request_type == 5) {
                $workDate = $request->request_for_date;
                $worksheet = Worksheet::where('work_date', 'like', $workDate)
                    ->where('member_id', Auth::user()->id)
                    ->first();

                $inOfficeHour = explode(':', $worksheet->in_office);
                if ($inOfficeHour[0] > 10) {
                    $actualOverTime = ($inOfficeHour[0] - 10) . ':' . $inOfficeHour[1];
                } else {
                    $actualOverTime = '00:00';
                }
                $request->actual_over_time = $actualOverTime;

                return $request;
            } else {
                return $request;
            }
        } else {
            return response()->json(['error' => 'This request is not available yet !'], Response::HTTP_NO_CONTENT);
        }
    }
}
