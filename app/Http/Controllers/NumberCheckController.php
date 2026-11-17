<?php

namespace App\Http\Controllers;

use App\Actions\PhoneNumbers;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Is this number already someone's lead?" A sales person handling a referral or a walk-in can find out
 * before adding it, without being shown other people's notes or call history.
 */
class NumberCheckController extends Controller
{
    public function index(Request $request, PhoneNumbers $phones): View
    {
        $this->employeeWithRole($request, ['Admin', 'Supervisor', 'Team Leader', 'Sales Executive', 'MarketingExecutive']);
        $input = trim((string) $request->query('phone', ''));
        $result = null;

        if ($input !== '') {
            $number = $phones->normalize($input);

            if ($number === null) {
                $result = ['valid' => false];
            } else {
                $lead = $phones->findLead($number);
                $lead?->load(['currentOwner:id,name', 'source:source_id,campaign_name,channel']);
                $result = [
                    'valid' => true,
                    'number' => $number,
                    'lead' => $lead,
                    'matchedAs' => $lead === null ? null : $phones->matchedAs($lead, $number),
                ];
            }
        }

        return view('number-check.index', ['input' => $input, 'result' => $result]);
    }
}
