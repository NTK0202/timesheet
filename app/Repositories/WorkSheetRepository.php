<?php

namespace App\Repositories;

use App\Models\Request;
use App\Models\Worksheet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class WorkSheetRepository extends BaseRepository
{
    public function getModel()
    {
        return Worksheet::class;
    }

    public function filter($request, $member_id)
    {
        $per_page = $request->per_page ?? config('default_per_page');
        $page = $request->page ?? 1;
        $end_date = $request->end_date ?? Carbon::now()->format('Y-m-d');

        $this->model = $this->model->where('member_id', $member_id);

        if (trim((string) $request->start_date) !== "") {
            $this->model = $this->model
                ->where('work_date', '>=', $request->start_date ?? "");
        }

        if ($end_date !== "") {
            $this->model = $this->model
                ->where('work_date', '<=', $end_date ?? "");
        }

        if (trim((string) $request->work_date) !== "") {
            $this->model = $this->model->orderBy('work_date', $request->work_date);
        } else {
            $this->model = $this->model->orderBy('work_date', 'desc');
        }

        return $this->model->paginate($per_page, ['*'], 'page', $page);
    }

    public function findRequest($date, $type)
    {
        if ($type == 6) {
            return Request::where('member_id', Auth::user()->id)
                ->where('request_for_date', $date)
                ->whereIn('request_type', [2, 3])
                ->first();
        }

        return Request::where('member_id', Auth::user()->id)
            ->where('request_for_date', $date)
            ->where('request_type', $type)
            ->first();
    }

    public function find($id, $request = null)
    {
        $worksheet = $this->model->where('id', $id)
            ->where('member_id', Auth::user()->id)
            ->first();

        if ($this->model->find($id)) {
            if ($worksheet) {
                $findRequest = $this->findRequest($worksheet->work_date, $request->type);

                if (!$findRequest) {
                    return $worksheet;
                } else {
                    $worksheet = Worksheet::where('work_date', 'like', $worksheet->work_date)
                        ->where('member_id', Auth::user()->id)
                        ->first();

                    $inOfficeHour = explode(':', $worksheet->in_office);
                    if ($inOfficeHour[0] > 10) {
                        $actualOverTime = ($inOfficeHour[0] - 10).':'.$inOfficeHour[1];
                    } else {
                        $actualOverTime = '00:00';
                    }

                    if($request->type == 5) {
                        return response()->json([
                            'request' => $findRequest,
                            'actual_over_time' => $actualOverTime
                        ]);
                    }
                    else {
                        return $findRequest;
                    }
                }
            }

            return response()->json(['message' => "You cannot access other people's worksheets"]);
        }

        return response()->json(['message' => 'This worksheet does not exist !']);
    }
}
