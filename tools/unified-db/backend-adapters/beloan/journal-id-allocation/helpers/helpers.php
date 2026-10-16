<?php

use App\Models\CoaCategory;
use App\Models\TransactionsRequiry;
use App\Models\Loan;
use App\Models\ClientLoanAccounts;
use App\Models\JournalDetail;
use App\Models\JournalRequiry;
use App\Models\CompanyBranch;
use App\Models\RepaymentSchedule;
use App\Models\LoanPayments;
use App\Models\User;

if (!function_exists('doc_asset')) {
    /**
     * URL for a file uploaded under public/data.
     *
     * asset() builds the URL against whichever host is serving the request, which is
     * wrong on UAT: the container has no volume on public/data, so every deploy
     * recreates it and the uploaded files are gone, leaving every old document a 404.
     * The files those rows name are still on the live host, so static_data.document_base_url
     * points there and the links resolve again.
     *
     * With that config empty this is just asset(), which is what a host that owns its own
     * uploads should do.
     *
     * Read-only: it changes where a document is READ from. Uploads still write locally.
     */
    function doc_asset($path, $secure = null)
    {
        $base = rtrim((string) config('static_data.document_base_url'), '/');

        // A file that exists here wins. New uploads land in the public/data volume and
        // have to be served from this host -- sending them to the old one would 404,
        // since they were never there. The remote base is only a fallback for the
        // legacy files this database still names, which live on the old host alone.
        if ($base === '' || file_exists(public_path($path))) {
            return asset($path, $secure);
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('UnEmptyDate')) {
    function UnEmptyDate($date)
    {
        if ((trim($date) != '0000-00-00') && !empty($date) && !is_null($date) && ($date != "")) {
            return date("Y-m-d", strtotime($date));
        } else {
            return '';
        }
    }
}
if (!function_exists('date_dif')) {
    /**
     *
     * @param string $start_date
     * @param string $end_date
     * @param int $type
     * type parameter 1: day,2:month,3:year
     * @param boolean $abs
     * @return int
     */
    function date_dif($start_date, $end_date, $type = 1, $abs = true)
    {
        $time_start = strtotime($start_date);
        $time_end = strtotime($end_date);
        $diff = ($abs == true) ? abs($time_end - $time_start) : ($time_end - $time_start);
        $divide = 60 * 60 * 24;
        if ($type == 2) {
            $divide = 30 * 60 * 60 * 24;
        } elseif ($type == 3) {
            $divide = 365 * 60 * 60 * 24;
        }
        return floor($diff / $divide);
    }
}
if (!function_exists('add_month')) {
    /**
     *
     * @param string $date_str
     * @param int $months
     * @return date
     */
    function add_month($date_str, $months, $frequency = '')
    {
        $date = new DateTime($date_str);
        $start_day = $date->format('j');
        $step = $months;
        $fre_name = '';
        switch ($frequency) {
            case '':
            case 'O':
            {
                $step *= 1;
                $fre_name = 'months';
                break;
            }
            case 'W':
            {
                $step *= 1;
                $fre_name = 'weeks';
                break;
            }
            case 'F':
            {
                $step *= 2;
                $fre_name = 'weeks';
                break;
            }
            case 'M':
            {
                $step *= 1;
                $fre_name = 'months';
                break;
            }
            case 'Q':
            {
                $step *= 3;
                $fre_name = 'months';
                break;
            }
            case 'H':
            {
                $step *= 6;
                $fre_name = 'months';
                break;
            }
            case 'Y':
            {
                $step *= 1;
                $fre_name = 'years';
                break;
            }
        }
        $date->modify("+{$step} {$fre_name}");
        $end_day = $date->format('j');

//        if ($start_day != $end_day)
//            $date->modify('last day of last month');
        return $date;
    }
}

if (!function_exists('date_except')) {
    /**
     *
     * @param string $str_date
     * @param array $except
     * @return string
     */
    function date_except($str_date, $except = array(), $dir = HOLIDAY_SH_DIRECTION)
    {
        $date = $str_date;
        $cur_ym = intval(date('Ym', strtotime($date)));
        $sat_shift = $sun_shift = $hol_shift = '';
        if ($dir == "FWD") {
            $sat_shift = '+ 2 days';
            $sun_shift = '+ 1 days';
            $hol_shift = '+ 1 days';
        } else {
            $sat_shift = '- 1 days';
            $sun_shift = '- 2 days';
            $hol_shift = '- 1 days';
        }
        $back_1day = '- 1 days';
        $back_2day = '- 2 days';
        if (date('D', strtotime($date)) == 'Sat') {
            $date = date('Y-m-d', strtotime($str_date . $sat_shift));
            $shift_ym = intval(date('Ym', strtotime($date)));
            // In case move to next month
            if ($shift_ym > $cur_ym) {
                $date = date('Y-m-d', strtotime($str_date . $back_1day));
            }
        } elseif (date('D', strtotime($date)) == 'Sun') {
            $date = date('Y-m-d', strtotime($str_date . $sun_shift));
            $shift_ym = intval(date('Ym', strtotime($date)));
            // In case move to next month
            if ($shift_ym > $cur_ym) {
                $date = date('Y-m-d', strtotime($str_date . $back_2day));
            }
        }
        if (is_array($except) && count($except) > 0) {
            foreach ($except as $key => $ex) {
                if (!empty($ex) && is_string($ex)) {
                    if (date_dif($date, $ex) == 0) {
                        $except2 = array_where($except, function ($k, $v) use ($key) {
                            if ($dir == 'BWD') {
                                return $k < $key;
                            }
                            return $k > $key;
                        });
                        return date_except(date('Y-m-d', strtotime($date . $hol_shift)), $except2, $dir);
                    }
                }
            }
        }
        return $date;
    }
}

if (!function_exists('date_diff_except_holiday')) {
    /**
     *
     * @param string $str_date
     * @param array $except
     * @return string
     */
    function date_diff_except_holiday($start_date, $end_date, $holiday_flag = 0, $except = array())
    {
        if (($num_date = date_dif($start_date, $end_date, 1, false)) <= 0) {
            return -1;
        }
        $day_count = 0;
        for ($i = 0; $i < $num_date; $i++) {
            $date = date('Y-m-d', strtotime($start_date . ' + ' . $i . ' days'));
            if (date('D', strtotime($date)) == 'Sat' || date('D', strtotime($date)) == 'Sun') {
                continue;
            }

            if ($holiday_flag != 0 && is_array($except) && count($except) > 0) {
                foreach ($except as $key => $ex) {
                    if (!empty($ex) && is_string($ex)) {
                        if (date_dif($date, $ex) == 0) {
                            continue 2;
                        }
                    }
                }
            }
            $day_count++;
        }
        return $day_count;
    }
}

if (!function_exists('khmerMonth')) {
    /**
     * @param string $en
     * @return string
     */
    function khmerMonth($en)
    {
        $month = [
            'jan' => 'មករា',
            'feb' => 'កុម្ភៈ',
            'mar' => 'មីនា',
            'apr' => 'មេសា',
            'may' => 'ឧសភា',
            'jun' => 'មិថុនា',
            'jul' => 'កក្កដា',
            'aug' => 'សីហា',
            'sep' => 'កញ្ញា',
            'oct' => 'តុលា',
            'nov' => 'វិច្ឆិកា',
            'dec' => 'ធ្នូ'
        ];
        return $month[strtolower($en)];
    }
}
if (!function_exists('khmerNumber')) {
    function khmerNumber($number)
    {
        $khmerNumber = ['០', '១', '២', '៣', '៤', '៥', '៦', '៧', '៨', '៩'];
        $dateNumber = (string)$number;
        $split = str_split($dateNumber, 1);
        $num_kh = '';
        foreach ($split as $num) {
            $num_kh .= isset($khmerNumber[$num]) ? $khmerNumber[$num] : $num;
        }
        return $num_kh;
    }
}

if (!function_exists('khNumberWord')) {
    function khNumberWord($num = false)
    {
        $num = str_replace(array(',', ' '), '', trim($num));
        if (!$num) {
            return false;
        }
        $num = (int)$num;
        $words = array();
        $list1 = array('', 'មួយ', 'ពីរ', 'បី', 'បួន', 'ប្រាំ', 'ប្រាំមួយ', 'ប្រាំពីរ', 'ប្រាំបី', 'ប្រាំបួន', 'ដប់', 'ដប់មួយ',
            'ដប់ពីរ', 'ដប់បី', 'ដប់បួន', 'ដប់ប្រាំ', 'ដប់ប្រាំមួយ', 'ដប់ប្រាំពីរ', 'ដប់ប្រាំបី', 'ដប់ប្រាំបួន'
        );
        $list2 = array('', 'ដប់', 'ម្ភៃ', 'សាមសិប', 'សែសិប', 'ហាសិប', 'ហុកសិប', 'ចិតសិប', 'ប៉ែតសិប', 'កៅសិប', 'រយ');

        $list3 = array('', 'ពាន់', 'លាន', 'ពាន់​លាន', 'សែនកោដិ', 'quadrillion', 'quintillion', 'sextillion', 'septillion',
            'octillion', 'nonillion', 'decillion', 'undecillion', 'duodecillion', 'tredecillion', 'quattuordecillion',
            'quindecillion', 'sexdecillion', 'septendecillion', 'octodecillion', 'novemdecillion', 'vigintillion'
        );
        $num_length = strlen($num);
        $levels = (int)(($num_length + 2) / 3);
        $max_length = $levels * 3;
        $num = substr('00' . $num, -$max_length);
        $num_levels = str_split($num, 3);
        for ($i = 0; $i < count($num_levels); $i++) {
            $levels--;
            $hundreds = (int)($num_levels[$i] / 100);
            $hundreds = ($hundreds ? '' . $list1[$hundreds] . 'រយ' . '' : '');
            $tens = (int)($num_levels[$i] % 100);
            $singles = '';
            if ($tens < 20) {
                $tens = ($tens ? '' . $list1[$tens] . '' : '');
            } else {
                $tens = (int)($tens / 10);
                $tens = '' . $list2[$tens] . '';
                $singles = (int)($num_levels[$i] % 10);
                $singles = '' . $list1[$singles] . '';
            }
            $words[] = $hundreds . $tens . $singles . (($levels && ( int )($num_levels[$i])) ? '' . $list3[$levels] . '' : '');
        } //end for loop
        $commas = count($words);
        if ($commas > 1) {
            $commas = $commas - 1;
        }
        return implode('', $words);
    }
}
if (!function_exists('khmerDay')) {
    /**
     * @param string $en
     * @return string
     */
    function khmerDay($en)
    {
        $day = [
            'mon' => 'ចន្ទ',
            'tue' => 'អង្គារ',
            'wed' => 'ពុធ',
            'thu' => 'ព្រហស្បតី៍',
            'fri' => 'សុក្រ',
            'sat' => 'សៅរ៍',
            'sun' => 'អាទិត្យ'
        ];
        return $day[strtolower($en)];
    }
}

if (!function_exists('khmerShortDay')) {
    /**
     * @param string $en
     * @return string
     */
    function khmerShortDay($en)
    {
        $day = [
            'mon' => 'ច',
            'tue' => 'អ',
            'wed' => 'ពុ',
            'thu' => 'ព្រ',
            'fri' => 'សុ',
            'sat' => 'សៅ',
            'sun' => 'អា'
        ];
        return $day[strtolower($en)];
    }
}

if (!function_exists('getArrear')) {
    /**
     * @param int $month_index
     * @param array $repayment_owed_tb
     * @return string
     */
    function getArrear($repayment_owed_tb, $month_index)
    {
        $arrear = 0;
        foreach ($repayment_owed_tb as $data) {
            if ($data['condition_id'] == 1) {//pay next time
                $month_index++;
            }
            if ($data['repayment_owed'] > 0 && $data['payment_month'] == $month_index - 1) {
                $arrear = $data['repayment_owed'];
            }
        }
        return $arrear;
    }
}
if (!function_exists('data_get_date')) {

    function data_get_date($target, $key, $date_start = '', $date_end = '', $default = null)
    {
        if (is_null($key)) return $target;

        foreach (explode('.', $key) as $segment) {
            if (is_array($target)) {
                if (!array_key_exists($segment, $target)) {
                    return value($default);
                }
                if (!empty($date_start) && !empty($date_end)) {
                    $start = date_dif(date('Y-m-d', strtotime($date_start)), date('Y-m-d', strtotime($target[$segment])), 1, false);
                    $end = date_dif(date('Y-m-d', strtotime($target[$segment])), date('Y-m-d', strtotime($date_end)), 1, false);
                    return $start >= 0 && $end >= 0 ? $target[$segment] : value($default);

                } elseif (!empty($date_start) && empty($date_end)) {
                    $start = date_dif(date('Y-m-d', strtotime($date_start)), date('Y-m-d', strtotime($target[$segment])), 1, false);
                    return $start >= 0 ? $target[$segment] : value($default);
                } elseif (empty($date_start) && !empty($date_end)) {
                    $end = date_dif(date('Y-m-d', strtotime($target[$segment])), date('Y-m-d', strtotime($date_end)), 1, false);
                    return $end >= 0 ? $target[$segment] : value($default);
                }
                $target = $target[$segment];
            } elseif ($target instanceof ArrayAccess) {
                if (!isset($target[$segment])) {
                    return value($default);
                }

                if (!empty($date_start) && !empty($date_end)) {
                    $start = date_dif(date('Y-m-d', strtotime($date_start)), date('Y-m-d', strtotime($target[$segment])), 1, false);
                    $end = date_dif(date('Y-m-d', strtotime($target[$segment])), date('Y-m-d', strtotime($date_end)), 1, false);
                    return $start >= 0 && $end >= 0 ? $target[$segment] : value($default);
                } elseif (!empty($date_start) && empty($date_end)) {
                    $start = date_dif(date('Y-m-d', strtotime($date_start)), date('Y-m-d', strtotime($target[$segment])), 1, false);
                    return $start >= 0 ? $target[$segment] : value($default);
                } elseif (empty($date_start) && !empty($date_end)) {
                    $end = date_dif(date('Y-m-d', strtotime($target[$segment])), date('Y-m-d', strtotime($date_end)), 1, false);
                    return $end >= 0 ? $target[$segment] : value($default);
                }

                $target = $target[$segment];
            } elseif (is_object($target)) {
                if (!isset($target->{$segment})) {
                    return value($default);
                }
                if (!empty($date_start) && !empty($date_end)) {
                    $start = date_dif(date('Y-m-d', strtotime($date_start)), date('Y-m-d', strtotime($target->{$segment})), 1, false);
                    $end = date_dif(date('Y-m-d', strtotime($target->{$segment})), date('Y-m-d', strtotime($date_end)), 1, false);
                    return $start >= 0 && $end >= 0 ? $target->{$segment} : value($default);
                } elseif (!empty($date_start) && empty($date_end)) {
                    $start = date_dif(date('Y-m-d', strtotime($date_start)), date('Y-m-d', strtotime($target->{$segment})), 1, false);
                    return $start >= 0 ? $target->{$segment} : value($default);
                } elseif (empty($date_start) && !empty($date_end)) {
                    $end = date_dif(date('Y-m-d', strtotime($target->{$segment})), date('Y-m-d', strtotime($date_end)), 1, false);
                    return $end >= 0 ? $target->{$segment} : value($default);
                }

                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }
}
if (!function_exists('locale_trans')) {
    function locale_trans($locale = null, $locale_code = '', $default = 'undefined')
    {
        if (!empty($locale) && !empty($locale_code)) {
            $loc = $locale->first(function ($key, $loc_title) use ($locale_code) {
                return $loc_title->key == $locale_code;
            });
            if (!empty($loc)) {
                return $loc->title;
            }
        }
        return $default;
    }
}

if (!function_exists('getJournalDetail')) {
    /**
     * @param
     * @param
     * @return
     */
    function getJournalDetail($params = array(),$type = 'loan')
    {
        $output = '<table class="journal-helper tb-search-box">';
        if ($params['entry_date']) {
            $output .= '<tr>';
            $output .= '<td>';
            $output .= '<span>Entry Date</span>';
            $output .= '<input type="text" name="entry_date[]" class="form-control" readonly="readonly" value="' . $params['entry_date'] . '" />';
            $output .= '<input type="hidden" name="branch_code[]" value="' . $params['branch_code'] . '" />';
            $output .= '</td>';

            $output .= '<td>';
            $output .= '<span>Invoice Number</span>';
            $output .= '<input type="text" name="invoice_number[]" class="form-control" />';
            $output .= '</td>';

            $output .= '<td>';
            $output .= '<span>Branch</span>';
            $output .= '<input type="text" name="branch" class="form-control" readonly="readonly" value="' . $params['branch_label'] . '" />';
            $output .= '<input type="hidden" name="branch_id[]" value="' . $params['branch'] . '" />';
            $output .= '</td>';

            $output .= '<td>';
            $output .= '<span>Currency</span>';
            $output .= '<input type="text" name="currency_label" class="form-control" readonly="readonly" value="' . $params['currency_label'] . '" />';
            $output .= '<input type="hidden" name="currency[]" value="' . $params['currency'] . '" />';
            $output .= '</td>';

            $output .= '<td></td>';
            $output .= '</tr>';
        }

        $output .= '<tr>';
        $output .= '<td colspan="2" class="h-parent_debit" width="600">';
        $output .= '<span>Debit</span>';
        $output .= '<input type="text" name="parent_debit_label" class="parent_debit_label_className form-control" readonly="readonly" value="' . $params['parent_debit_label'] . '" />';
        $output .= '<input type="hidden" class="parent_debit_className" name="parent_debit[]" value="' . $params['parent_debit'] . '" />';
        $output .= '</td>';

        if ($params['contract_id']) {
            $output .= '<td>';
            $output .= '<span>Contract ID</span>';
            $output .= '<input type="text" name="contract_id[]" class="form-control" readonly="readonly" value="' . $params['contract_id'] . '" />';
            $output .= '</td>';
        }
        $output .= '<td>';
        $output .= '<span>Debit</span>';
        $output .= '<input type="text" name="debit[]" class="form-control parent_debit_className_value  " readonly="readonly" value="' . $params['debit'] . '" />';
        $output .= '</td>';

        $output .= '<td>';
        $output .= '<span>Description</span>';
        $output .= '<input type="text" name="d_description[]" class="form-control" value="' . $params['d_description'] . '" />';
        $output .= '</td>';

        $output .= '</tr>';

        $output .= '<tr>';
        $output .= '<td colspan="2" class="h-parent_credit">';
        $output .= '<span>Credit</span>';
        $output .= '<input type="text" name="parent_credit_label" class="form-control parent_credit_label_className" readonly="readonly" value="' . $params['parent_credit_label'] . '" />';
        $output .= '<input type="hidden" class="parent_credit_className" name="parent_credit[]" value="' . $params['parent_credit'] . '" />';
        $output .= '</td>';

        if ($params['contract_id']) {
            $output .= '<td>';
            $output .= '<span>Contract ID</span>';
            $output .= '<input type="text" name="credit_contract_id" class="form-control" readonly="readonly" value="' . $params['contract_id'] . '" />';
            $output .= '</td>';
        }

        $output .= '<td>';
        $output .= '<span>Credit</span>';
        $output .= '<input type="text" name="credit[]" class="form-control parent_credit_className_value" readonly="readonly" value="' . $params['credit'] . '" />';
        $output .= '</td>';

        $output .= '<td>';
        $output .= '<span>Description</span>';
        $output .= '<input type="text" name="c_description[]" class="form-control" value="' . $params['c_description'] . '" />';
        $output .= '</td>';
        $output .= '</tr>';

        $output .= '<tr>';
        $output .= '<td colspan="5">';
        $output .= '<span>Description</span>';
        $output .= '<textarea name="description[]" class="form-control">' . $params['description'] . '</textarea>';
        $output .= '</td>';
        $output .= '</tr>';
        $output .= '</table>';
        return $output;
    }

    if (!function_exists('getJournalDetailDownpayment')) {
        function getJournalDetailDownpayment($params = array(),$type = 'loan'){
            $output = '<table class="journal-helper tb-search-box">';
            $output .= '<tr>';
                $output .= '<td colspan="2" class="h-parent_debit_down" width="600">';
                $output .= '<span>Debit</span>';
                $output .= '<input type="text" name="parent_debit_label_down" class="parent_debit_label_down_className form-control" readonly="readonly" value="' . $params['parent_debit_label_down'] . '" />';
                $output .= '<input type="hidden" class="parent_debit_down_className" name="parent_debit_down[]" value="' . $params['parent_debit_down'] . '" />';
                $output .= '</td>';
            $output .= '<td>';
            $output .= '<span>Debit</span>';
            $output .= '<input type="text" name="debit_down[]" class="form-control parent_debit_down_className_value  " readonly="readonly" value="' . $params['debit_down'] . '" />';
            $output .= '</td>';

            $output .= '<td>';
            $output .= '<span>Description</span>';
            $output .= '<input type="text" name="d_description_down[]" class="form-control" value="' . $params['d_description_down'] . '" />';
            $output .= '</td>';

            $output .= '</tr>';

            $output .= '<tr>';
            $output .= '<td colspan="2" class="h-parent_credit_down">';
            $output .= '<span>Credit</span>';
            $output .= '<input type="text" name="parent_credit_down_label" class="form-control parent_credit_down_label_className" readonly="readonly" value="' . $params['parent_credit_down_label'] . '" />';
            $output .= '<input type="hidden" class="parent_credit_down_className" name="parent_credit_down[]" value="' . $params['parent_credit_down'] . '" />';
            $output .= '</td>';
            $output .= '<td>';
            $output .= '<span>Credit</span>';
            $output .= '<input type="text" name="credit_down[]" class="form-control parent_credit_down_className_value" readonly="readonly" value="' . $params['credit_down'] . '" />';
            $output .= '</td>';

            $output .= '<td>';
            $output .= '<span>Description</span>';
            $output .= '<input type="text" name="c_description_down[]" class="form-control" value="' . $params['c_description_down'] . '" />';
            $output .= '</td>';
            $output .= '</tr>';

            $output .= '<tr>';
            $output .= '<td colspan="5">';
            $output .= '<span>Description</span>';
            $output .= '<textarea name="description_down[]" class="form-control">' . $params['description_down'] . '</textarea>';
            $output .= '</td>';
            $output .= '</tr>';
            $output .= '</table>';

            return $output;
        }
    }

    if (!function_exists('createLoanAccountCoa')) {
        /**
         * @param
         * @param
         * @return
         */
        function createLoanAccountCoa($loan_account, $pre_prefix = "", $parent_id = null, $account_code = null, $account_name = null)
        {
            $new_coa = [];
            if ($pre_prefix == "Drawdown Account") {
                $parent_name = $pre_prefix;
            } else {
                $parent_name = $pre_prefix . $loan_account->prefix . $loan_account->acc_key;
            }
            $new_coa = new CoaCategory();
            $new_coa->id = CoaCategory::select('id')->orderBy('id', 'desc')->first()->id + 1;
            if (is_null($parent_id)) {
                if ($pre_prefix == "Int-in-Suspense-") {
                    if ($loan_account->prefix == "Stand-L-") $pre_prefix = "Inc-Int-";
                    $pat1 = $pre_prefix . $loan_account->prefix . '%';
                    $pat3 = '%' . substr($loan_account->acc_key, 0, 7) . '%';
                    if (strpos($loan_account->acc_key, '<') !== false) {
                        $pat2 = '%<%';
                    } elseif (strpos($loan_account->acc_key, '>') !== false) {
                        $pat2 = '%>%';
                    }
                    $parent_id = CoaCategory::select('id', 'name', 'type', 'currency')->whereRaw("`name` LIKE '{$pat1}' and `name` LIKE '{$pat2}' and `name` LIKE '{$pat3}'")
                        ->where('type', '=', 6)->where('currency', '=', $loan_account->currency)->first()->id;
                } else {
                    $parent_id = CoaCategory::select('id', 'name', 'type', 'currency')->where('name', 'LIKE', $parent_name . '%')->where('type', '=', 6)->where('currency', '=', $loan_account->currency)->first()->id;
                }
            }
            $coa = CoaCategory::select('*')->where('id', '=', $parent_id)->first();
            $new_coa->nbc_code = $coa->nbc_code;
            if (is_null($account_code)) {
                if ($pre_prefix == "Int-in-Suspense-") {
                    $account_code = substr($coa->account_code, 0, 14) . '-' . substr($loan_account->account_no, 14, 4);
                } else {
                    $account_code = substr($coa->account_code, 0, 10) . substr($loan_account->account_no, 14, 4);
                }
            }
            $new_coa->parent_id = $coa->id;
            $new_coa->account_code = $account_code;
            $new_coa->currency = $loan_account->currency;
            $new_coa->sector_id = $coa->sector_id;
            $new_coa->type = 7;
            $kw = "";
            if ($pre_prefix == "Drawdown Account") {
                $description = $pre_prefix . " - " . $account_name;
                $kw = "%" . $pre_prefix . "%" . $account_name . "%";
            } else {
                $description = $coa->name . " - " . $loan_account->account_name;
                $kw = "%" . $coa->name . "%" . $loan_account->account_name . "%";
            }

            $new_coa->description = $description;
            $new_coa->name = $new_coa->description;
            $exist_acc = CoaCategory::where('account_code', '=', $new_coa->account_code)->where('name', 'LIKE', $kw)->where('type', '=', 7)->where('currency', '=', $new_coa->currency)->first();
            //dd($exist_acc);
            if (!is_null($exist_acc)) {
                return ['coa' => $exist_acc, 'new_flg' => 0];
            } else {
                $new_coa->save();
                return ['coa' => $new_coa, 'new_flg' => 1];
            }
            /*
                        $new_coa->save();
                        return ['coa'=>$new_coa,'new_flg'=>1];
            */
        }
    }
    if (!function_exists('createLoanAccountCoaNew')) {
        /**
         * @param
         * @param
         * @return
         */
        function createLoanAccountCoaNew($loan_account, $pre_prefix = "", $parent_id = null, $account_code = null, $account_name = null)
        {
            $new_coa = [];
            if ($pre_prefix == "Drawdown Account") {
                $parent_name = $pre_prefix;
            } else {
                $parent_name = $pre_prefix . $loan_account->prefix . $loan_account->acc_key;
            }
            $new_coa = new CoaCategory();
            $new_coa->id = CoaCategory::select('id')->orderBy('id', 'desc')->first()->id + 1;
            if (is_null($parent_id)) {
                if ($pre_prefix == "Int-in-Suspense-") {
                    if ($loan_account->prefix == "Stand-L-") $pre_prefix = "Inc-Int-";
                    $pat1 = $pre_prefix . $loan_account->prefix . '%';
                    $pat3 = '%' . substr($loan_account->acc_key, 0, 7) . '%';
                    if (strpos($loan_account->acc_key, '<') !== false) {
                        $pat2 = '%<%';
                    } elseif (strpos($loan_account->acc_key, '>') !== false) {
                        $pat2 = '%>%';
                    }
                    $parent_id = CoaCategory::select('id', 'name', 'type', 'currency')->whereRaw("`name` LIKE '{$pat1}' and `name` LIKE '{$pat2}' and `name` LIKE '{$pat3}'")
                        ->where('type', '=', 6)->where('currency', '=', $loan_account->currency)->first()->id;
                } else {
                    $parent_id = CoaCategory::select('id', 'name', 'type', 'currency')->where('name', 'LIKE', $parent_name . '%')->where('type', '=', 6)->where('currency', '=', $loan_account->currency)->first()->id;
                }
            }
            $coa = CoaCategory::select('*')->where('id', '=', $parent_id)->first();
            $new_coa->nbc_code = $coa->nbc_code;
            if (is_null($account_code)) {
                if ($pre_prefix == "Int-in-Suspense-") {
                    // $account_code = substr($coa->account_code,0,14) .'-'. substr($loan_account->account_no, 14, 4);
                    $account_code = substr($coa->account_code, 0, 14) . '-' . substr($loan_account->account_no, 4, 6);
                } else {
                    // $account_code = substr($coa->account_code,0,10) . substr($loan_account->account_no, 14, 4);
                    $account_code = substr($coa->account_code, 0, 10) . substr($loan_account->account_no, 4, 6);
                }
            }
            $new_coa->parent_id = $coa->id;
            $new_coa->account_code = $account_code;
            $new_coa->currency = $loan_account->currency;
            $new_coa->sector_id = $coa->sector_id;
            $new_coa->type = 7;
            $kw = "";
            if ($pre_prefix == "Drawdown Account") {
                $description = $pre_prefix . " - " . $account_name;
                $kw = "%" . $pre_prefix . "%" . $account_name . "%";
            } else {
                $description = $coa->name . " - " . $loan_account->account_name;
                $kw = "%" . $coa->name . "%" . $loan_account->account_name . "%";
            }
            $new_coa->description = $description;
            $new_coa->name = $new_coa->description;
            $exist_acc = CoaCategory::where('account_code', '=', $new_coa->account_code)->where('name', 'LIKE', $kw)->where('type', '=', 7)->where('currency', '=', $new_coa->currency)->first();
            if (!is_null($exist_acc)) {
                return ['coa' => $exist_acc, 'new_flg' => 0];
            } else {
                $new_coa->save();
                return ['coa' => $new_coa, 'new_flg' => 1];
            }
            /*
                        $new_coa->save();
                        return ['coa'=>$new_coa,'new_flg'=>1];
            */
        }
    }

    if (!function_exists('createLoanAccountCoaNewMigrate')) {
        /**
         * @param
         * @param
         * @return
         */
        function createLoanAccountCoaNewMigrate($loan_account, $pre_prefix = "", $parent_id = null, $account_code = null, $account_name = null)
        {
            $new_coa = [];
            if ($pre_prefix == "Drawdown Account") {
                $parent_name = $pre_prefix;
            } else {
                $parent_name = $pre_prefix . $loan_account->prefix . $loan_account->acc_key;
            }
            $new_coa = new CoaCategory();
            $new_coa->id = CoaCategory::select('id')->orderBy('id', 'desc')->first()->id + 1;
            if (is_null($parent_id)) {
                if ($pre_prefix == "Int-in-Suspense-") {
                    if ($loan_account->prefix == "Stand-L-") $pre_prefix = "Inc-Int-";
                    $pat1 = $pre_prefix . $loan_account->prefix . '%';
                    $pat3 = '%' . substr($loan_account->acc_key, 0, 7) . '%';
                    if (strpos($loan_account->acc_key, '<') !== false) {
                        $pat2 = '%<%';
                    } elseif (strpos($loan_account->acc_key, '>') !== false) {
                        $pat2 = '%>%';
                    }
                    $parent_id = CoaCategory::select('id', 'name', 'type', 'currency')->whereRaw("`name` LIKE '{$pat1}' and `name` LIKE '{$pat2}' and `name` LIKE '{$pat3}'")
                        ->where('type', '=', 6)->where('currency', '=', $loan_account->currency)->first()->id;
                } else {
                    $parent_id = CoaCategory::select('id', 'name', 'type', 'currency')->where('name', 'LIKE', $parent_name . '%')->where('type', '=', 6)->where('currency', '=', $loan_account->currency)->first()->id;
                }
            }
            $coa = CoaCategory::select('*')->where('id', '=', $parent_id)->first();
            $new_coa->nbc_code = $coa->nbc_code;
            if (is_null($account_code)) {
                if ($pre_prefix == "Int-in-Suspense-") {
                    $account_code = substr($coa->account_code,0,14) .'-'. $loan_account->account_no;
                } else {
                    $account_code = substr($coa->account_code,0,10) . $loan_account->account_no;
                }
            }
            $new_coa->parent_id = $coa->id;
            $new_coa->account_code = $account_code;
            $new_coa->currency = $loan_account->currency;
            $new_coa->sector_id = $coa->sector_id;
            $new_coa->type = 7;
            $kw = "";
            if ($pre_prefix == "Drawdown Account") {
                $description = $pre_prefix . " - " . $account_name;
                $kw = "%" . $pre_prefix . "%" . $account_name . "%";
            } else {
                $description = $coa->name . " - " . $loan_account->account_name;
                $kw = "%" . $coa->name . "%" . $loan_account->account_name . "%";
            }
            $new_coa->description = $description;
            $new_coa->name = $new_coa->description;
            $exist_acc = CoaCategory::where('account_code', '=', $new_coa->account_code)->where('name', 'LIKE', $kw)->where('type', '=', 7)->where('currency', '=', $new_coa->currency)->first();
            if (!is_null($exist_acc)) {
                return ['coa' => $exist_acc, 'new_flg' => 0];
            } else {
                $new_coa->save();
                return ['coa' => $new_coa, 'new_flg' => 1];
            }
            /*
                        $new_coa->save();
                        return ['coa'=>$new_coa,'new_flg'=>1];
            */
        }
    }

    if (!function_exists('round_num')) {
        function round_num($num, $round = null)
        {
            if ($round) {

            } else {
                //work with file
                $n = "global.php";
                $l = fopen($n, "a+");
                while (($line = fgets($l)) !== false) {
                    $ex = explode(', ', $line);
                    $ex0 = explode('"', $ex[0]);
                    $ex1 = explode(')', $ex[1]);
                    $ex2 = explode('"', $ex1[0]);
                    $ex1[0] = is_numeric($ex1[0]) ? $ex1[0] : $ex2[1];

                    if ($ex0[1] == 'ROUND_NUM') {
                        $round = $ex1[0];
                        break;
                    }
                }
            }

            if ($round == 'UP') {
                $num = ceil($num);
            } elseif ($round == 'DOWN') {
                $num = floor($num);
            } else {
                $num = round($num, 2);
            }

            return $num;
        }
    }

    //@chuch calc rate / intraday_rate
    if (!function_exists('calc_rate')) {
        function calc_rate($amount, $principal_balance, $int_rate, $type)
        {
            if ($type == 1 || $type == 3 || $type == 7) { // Declining
                $intraday_rate = $principal_balance * ($int_rate / 100) * 12 / YEAR_DAY;
            } else { // Flat
                $intraday_rate = $amount * ($int_rate / 100) * 12 / YEAR_DAY;;
            }
            return $intraday_rate;
        }
    }

    if (!function_exists('get_intraday_rate')) {
        function get_intraday_rate($l)
        {
            if (SYS_INT_RATE_FREQ == "Y") { // Yearly
                return $l->interest_rate / (YEAR_DAY * 100);
            } else { // Monthly
                return $l->interest_rate * 12 / (YEAR_DAY * 100);
            }
        }
    }
    //@Chamroeun get_repayment_table
    if (!function_exists('get_repayment_table')) {
        function get_repayment_table($loan, $last_paid_date, $total_paid_prin, $total_paid_int, $total_penalty, $owed_penalty = 0, $total_fee = 0, $repay_date = null, $key = "")
        {
            $repayment_table = [];
            $repayment_tmp = [];
            $sch_interest_array = [];
            $penalty_arr_result = [];
            $principal_arr_result = [];
            $penalty_arr = [];
            $index = 0;
            $amount_left = 0.0;
            if (is_null($repay_date)) $repay_date = date('Y-m-d');
            $schedule = $loan->schedule;
            $month_idx = date_dif($schedule[0]->schedule_date, $repay_date, 2) + 1;
            // schedule interest
            if ($total_paid_int > 0) {
                $total_sch_int_amount = 0.0;
                $sch_interest_array = get_sch_interest_array($loan->disburse_date, $schedule, $loan, 0, $repay_date, "repay");
                $sch_interest_left = $sch_interest_array;
                foreach ($sch_interest_array as $sch) {
                    foreach ($loan->payment as $pay) {
                        if ($sch[0] == $pay->payment_month) {
                            $sch_interest_left[$sch[0] - 1][3] -= $pay->paid_interest;
                            $sch_interest_left[$sch[0] - 1][4] = $sch[4];
                        }
                    }
                }
                foreach ($sch_interest_left as $sch_int) {
                    $total_sch_int_amount += $sch_int[3];
                    $index = $sch_int[0];
                    if ($total_paid_int > $total_sch_int_amount) {
                        $repayment_table[$index]['interest'] = $repayment_table[$index]['total'] = $sch_int[3];
                        $repayment_table[$index]['overdue'] = $sch_int[4];
                        //$amount_left = round(($total_paid_int - $total_sch_int_amount)*100)/100;
                        $amount_left = $total_paid_int - $total_sch_int_amount;
//                        var_dump($repayment_table);
                    } else {
                        if ($index < $month_idx && $amount_left > 0) {
                            $repayment_table[$index]['interest'] = $repayment_table[$index]['total'] = $amount_left;
                            $repayment_table[$index]['overdue'] = $sch_int[4];
                        } else {
                            $repayment_table[$index]['interest'] = $repayment_table[$index]['total'] = $total_paid_int;
                            $repayment_table[$index]['overdue'] = $sch_int[4];
                        }

                        break;
                    }
//                    var_dump($repayment_table); var_dump(' # ');
                }
            }
            // fee
            $fee_arr_result = get_sch_fee_array($loan, $repay_date);
            if ($total_fee > 0) {
                foreach ($fee_arr_result as $key => $s_fee) {
                    if ($s_fee[1] <= 0) continue;
                    $index = $s_fee[0];
                    if ($total_fee > $s_fee[1]) {
                        $repayment_table[$index]['fee'] = $s_fee[1];
                        $repayment_table[$index]['total'] += $repayment_table[$index]['fee'];
                        $total_fee -= $s_fee[1];
                    } else {
                        $repayment_table[$index]['fee'] = $total_fee;
                        $repayment_table[$index]['total'] += $repayment_table[$index]['fee'];
                        break;
                    }
                }
            }
            // penalty
            $penalty_arr_result = LoanCalculate::getTotalPenalty($loan, $repay_date)[0];
            foreach ($penalty_arr_result as $key => $penalty) {
                //array_push($penalty_arr, [$key, $penalty[6], $penalty[7], $penalty[8]]);
                $penalty_arr[$key] = [$penalty[6], $penalty[7], $penalty[8]];
            }
            if ($total_penalty > 0) {
                $total_sch_penalty_amount = 0.0;
                $amount_left = 0.0;
                $amount_paid = 0.0;
                foreach ($penalty_arr as $key => $penal) {
                    $index = $key;
                    $total_sch_penalty_amount += $penal[2];
                    // $amount_left = round(($total_penalty - $amount_paid)*100)/100;
                    $amount_left = $total_penalty - $amount_paid;
                    if ($amount_left >= 0) {
                        //array_push($repayment_table[$penal[0]], [$penal[3]]);
                        if ($amount_left > $penal[2]) {
                            $repayment_table[$index]['penalty'] = $penal[2];
                        } else {
                            $repayment_table[$index]['penalty'] = $amount_left;
                        }
                        $repayment_table[$index]['total'] += $repayment_table[$index]['penalty'];
                        $repayment_table[$index]['overdue'] = $penal[1];
                        $amount_paid += $repayment_table[$index]['penalty'];
                    } else {
                        $repayment_table[$index]['overdue'] = $penal[1];
                    }
                }
            }
            // principal
            if ($total_paid_prin > 0) {
                $schedule_arr_result = get_sch_principal_array($loan, $repay_date);
                $total_sch_principal_amount = 0.0;
                $amount_left = 0.0;
                foreach ($schedule_arr_result as $sch_pri) {
                    $total_sch_principal_amount += $sch_pri[1];
                    $index = $sch_pri[0];
                    //var_dump($repayment_table[$index]['total']);
                    if ($total_paid_prin >= $total_sch_principal_amount) {
                        $repayment_table[$sch_pri[0]]['principal'] = $sch_pri[1];
                        $repayment_table[$sch_pri[0]]['total'] += $sch_pri[1];
                        // $amount_left = round(($total_paid_prin - $total_sch_principal_amount)*100)/100;
                        $amount_left = $total_paid_prin - $total_sch_principal_amount;
                    } else {
                        if ($index < $month_idx && $amount_left > 0) {
                            $repayment_table[$index]['principal'] = $amount_left;
                            $repayment_table[$index]['total'] += $amount_left;
                        } else {
                            $repayment_table[$index]['principal'] = $total_paid_prin;
                            $repayment_table[$index]['total'] += $total_paid_prin;
                        }
                        break;
                    }

                    if (intval(floatval($repayment_table[$index]['total']) * 100) <= 0) {
                        // dd($repayment_table);
                        unset($repayment_table[$index]);
                    }
                }
            }
            //dd($repayment_table);
            // total loan payment(principal + interest)
            $payment_arr = [];
            foreach ($loan->payment as $pay) {
                $payment_arr[$pay->payment_month] += floatval($pay->paid_interest) + floatval($pay->paid_principal) + floatval($pay->paid_penalty) + floatval($pay->paid_fee);
            }
            foreach ($repayment_table as $idx => $repay) {
                if (intval((floatval($repay['total']) * 100)) == 0) {
                    unset($repayment_table[$idx]);
                    continue;
                }

                foreach ($schedule as $key => $sch) {
                    if ($idx != $sch->no) continue;
                    $amount_owed = ($sch->principal + $sch->interest + $penalty_arr[$idx][2] + $sch->fee) - $repayment_table[$idx]['total'];
                    if (!empty($payment_arr[$idx])) {
                        $amount_owed -= $payment_arr[$idx];
                    }
                    if (round($amount_owed, 2) <= 0) {
                        $repayment_table[$idx]['status'] = 1; // completed
                    } else {
                        $repayment_table[$idx]['status'] = 0; // Owed
                        $repayment_table[$idx]['repayment_owed'] = $amount_owed; // owed_amount
                        $repayment_table[$idx]['condition'] = 1; // Pay next time
                    }
                }
            }
            return $repayment_table;
        }
    }

    if (!function_exists('get_sch_interest_array')) {
        function get_sch_interest_array($last_paid_date, $schedule, $loan, $flag = 0, $paydate = null, $key = "")
        {
            $sch_interest_array = [];
            $amount = 0;
            $month_idx = 0;
            $i = -1;
            is_null($paydate) ? $current_date = date('Y-m-d') : $current_date = date('Y-m-d', strtotime($paydate));

            foreach ($schedule as $sch) {

                $i++;
                $days_diff = date_dif($last_paid_date, $sch->schedule_date, 1, false);

                $days_till_today = date_dif($last_paid_date, $current_date, 1, false);

                $current_month_idx = get_month_idx($loan->disburse_date, $current_date, $schedule);
                if ($days_diff >= 0.0) {
                    // last_paid_date --- current_date --- next schedule date
                    if (date_dif(date('Y-m-d', strtotime($sch->schedule_date)), $current_date, 1, false) < 0) {
                        $sch_pre = $schedule[$i - 1];
                        $days_diffLastM = date_dif(date("y-m-d", strtotime($current_date)), date("Y-m-d", strtotime($sch_pre->schedule_date)), 1, true);
                        if ($days_diffLastM <= 0) break;
                        $interest_amount = $days_diffLastM * $sch->intraday_rate;
//                        $month_idx = date_dif($loan->disburse_date, $current_date, 2);
                        $month_idx = get_month_idx($loan->disburse_date, $current_date, $schedule);
                        if ($month_idx == -1) break;
                        array_push($sch_interest_array, [$month_idx, $days_diff, $sch->intraday_rate, $interest_amount, $days_till_today]);
                        break;
                        //
                    } elseif ($days_diff < $sch->date_num) {
                        $interest_amount = $days_diff * $sch->intraday_rate;
//                        $month_idx = date_dif($loan->disburse_date, date('Y-m-d', strtotime($sch->schedule_date)), 2);
                        $month_idx = get_month_idx($loan->disburse_date, $sch->schedule_date, $schedule);
                        if ($month_idx == -1) break;
                        array_push($sch_interest_array, [$month_idx, $days_diff, $sch->intraday_rate, $interest_amount, $days_till_today]);
                        if ($key == "repay") break;
                        $days_diff = date_dif(date('Y-m-d', strtotime($sch->schedule_date)), $current_date, 1, false);
                        if ($days_diff > 0 && !empty($schedule[$i + 1])) {
                            $sch_1 = $schedule[$i + 1];
                            $interest_amount = $days_diff * $sch_1->intraday_rate;
                            $month_idx++;
                            array_push($sch_interest_array, [$month_idx, $days_diff, $sch->intraday_rate, $interest_amount, $days_till_today - $days_diff]);
                        }
                        break;
                    } else {
                        // last_paid_date --- next schedule date ---  current_date
                        if ($days_diff <= $days_till_today) {
                            $interest_amount = $sch->interest;
                            //$month_idx = date_dif($loan->start_date, $sch->schedule_date, 2);
                            $month_idx = get_month_idx($loan->disburse_date, $sch->schedule_date, $schedule);
                            if ($month_idx == -1) break;
                            array_push($sch_interest_array, [$month_idx, $sch->date_num, $sch->intraday_rate, $interest_amount, $days_till_today - $days_diff]);
                            $days_diff1 = date_dif($sch->schedule_date, $current_date, 1, false);
                            if ($days_diff1 == 0) break;
                            // maturity month
                            if (is_null($schedule[$i + 1])) continue;
                            $sch_1 = $schedule[$i + 1];
                            if ($key == "repay" && $days_diff1 < $sch_1->date_num) break;
                            if ($days_diff1 < $sch_1->date_num || $sch_1->schedule_date == $schedule[count($schedule) - 1]->schedule_date) {
                                $interest_amount = $days_diff1 * $sch_1->intraday_rate;

                                $month_idx++;
                                $overdue = 0;
                                if ($month_idx < $current_month_idx) {
                                    $overdue = $days_till_today - $days_diff;
                                }
                                if ($flag == 1) {
                                    $interest_amount = ceil($interest_amount);
                                }
                                array_push($sch_interest_array, [$month_idx, $days_diff1, $sch_1->intraday_rate, $interest_amount, $overdue]);

                                break;
                            }
                        }
                    }
                }
            }
            //return $dd;
            return $sch_interest_array;
        }
    }

    if (!function_exists('get_month_idx')) {
        function get_month_idx($disburse_date, $current_date, $schedule)
        {
            if (date_dif($disburse_date, $current_date, 1, false) < 0) return -1;
            foreach ($schedule as $key => $sch) {
                if (date_dif($sch->schedule_date, $current_date, 1, false) < 0) {
                    //return $key-1;
                    return $sch->no - 1;
                }
            }
            // maturity month
            if (date_dif($schedule[count($schedule) - 1]->schedule_date, $current_date, 1, false) >= 0) {
                //return count($schedule)-1;

                return $schedule[count($schedule) - 1]->no;
            }
            return -1;
        }
    }
    if (!function_exists('get_sch_principal_array')) {
        function get_sch_principal_array($loan, $paydate = null)
        {
            $payment = $loan->payment;
            $schedule = $loan->schedule;
            $sch_principal = [];
            $month_idx = 0;
            is_null($paydate) ? $today = date('Y-m-d') : $today = date('Y-m-d', strtotime($paydate));
            $month_idx_till_today = get_month_idx($loan->disburse_date, $today, $loan->schedule);
            foreach ($schedule as $sch) {
                if ($sch->no > $month_idx_till_today) break;
                $sch_prin = $sch->principal;
                foreach ($payment as $pay) {
                    if ($sch->no == $pay->payment_month) {
                        $sch_prin -= $pay->paid_principal;
                    }
                }
                array_push($sch_principal, [$sch->no, $sch_prin]);

            }
            return $sch_principal;
        }
    }

    if (!function_exists('get_sch_fee_array')) {
        function get_sch_fee_array($loan, $paydate = null, $opt = REPAY_CAL)
        {
            $payment = $loan->payment;
            $schedule = $loan->schedule;
            $sch_fee_arr = [];
            $month_idx = 0;

            is_null($paydate) ? $today = date('Y-m-d') : $today = date('Y-m-d', strtotime($paydate));
            $month_idx_till_today = get_month_idx($loan->disburse_date, $today, $loan->schedule);
            //dd($month_idx_till_today);
            $sch_no = 0;
            $key_no = 0;
            $t_sch_fee = $t_paid_fee = 0;
            foreach ($schedule as $key => $sch) {
                if ($sch->no > $month_idx_till_today) break;
                $sch_fee = $sch->fee;
                $t_sch_fee += $sch_fee;
                foreach ($payment as $pay) {
                    if ($sch->no == $pay->payment_month) {
                        $sch_fee -= $pay->paid_fee;
                        $t_paid_fee += $pay->paid_fee;
                    }
                }
                array_push($sch_fee_arr, [$sch->no, $sch_fee]);
                if ($opt == "SCH") {
                    $sch_date = $sch->schedule_date;
                    $sch_no = $sch->no;
                    $key_no = $key;
                }
            }
            if ($opt == "SCH") {
                $add_date = date_dif($sch_date, $paydate, 1, false);
                if ($add_date > 0) {
                    if (!is_null($schedule[$key + 1])) {
                        $sch_fee = $schedule[$key]->fee * $add_date / $schedule[$key]->date_num;
                        array_push($sch_fee_arr, [$key, $sch_fee]);
                    }
                }
            }
            return $sch_fee_arr;
        }
    }

    if (!function_exists('schedule_sort_by_no')) {
        function schedule_sort_by_no($schedule)
        {
            $sort_schedule = [];
            foreach ($schedule as $sch) {
                $sort_schedule[$sch->no] = $sch;
            }
            return $sort_schedule;
        }
    }

    if (!function_exists('get_auto_repay_array_downpayment')) {
        function get_auto_repay_array_downpayment($loan, $paydate = null)
        {
            $payment = $loan->payment;
            //$schedule = $loan->schedule;
            is_null($paydate) ? $today = date('Y-m-d') : $today = date('Y-m-d', strtotime($paydate));
            $repayment_sch = RepaymentSchedule::where('loan_id',$loan->id)->where('schedule_date','<=',$paydate)->get();
            $schedule = schedule_sort_by_no($repayment_sch);
            $sch_repay_arr = [];
            $is_penalty_no = [];
//            $month_idx = $loan->schedule[1]->no;
            $month_idx = 0;
            // $month_idx_till_today = get_month_idx($loan->disburse_date, $today, $loan->schedule);
            $month_idx_till_today = get_month_idx($loan->stat_payment_date, $today, $loan->schedule);
            foreach ($schedule as $key => $sch) {
                if ($sch->no > $month_idx_till_today) break;
                $sch_interest = $sch->interest;
                //if(count($schedule)-1 == $key){
                //    dd($loan->loan_duration);
                // if($loan->loan_duration == $key){
                //     $sch_interest = get_last_sch_int($loan->loan_account_id, $loan->transaction, $loan->interest_rate, $today);
                // }
                $sch_fee = $sch->fee;
                $sch_other_fee = $sch->other_fee;
                $sch_principal = $sch->principal;
                $sch_penalty = 0;
                $sch_monthly = 0;
                foreach ($payment as $pay) {
                    if ($sch->no == $pay->payment_month) {
                        $sch_interest -= $pay->paid_interest;
                        $sch_fee -= $pay->paid_fee;
                        $sch_other_fee -= $pay->paid_other_fee;
                        $sch_principal -= $pay->paid_principal;
                        $sch_penalty = 0;
                        if ($pay->status == 0) { // if owed
                            $sch_monthly = $sch_interest + $sch_fee + $sch_other_fee + $sch_principal;
                            $sch_penalty = $pay->repayment_owed - $sch_monthly - $sch_other_fee;
                            $num_date = date_dif($sch->schedule_date, $today, 1, false);
                            $bd_diff = date_dif($sch->schedule_date, $pay->repayment_date, 1, false);
                            if ($bd_diff <= $loan->penalty_period1) {
                                $d_diff = date_dif($sch->schedule_date, $today, 1, false);
                            } else {
                                $d_diff = date_dif($pay->repayment_date, $today, 1, false);
                            }
                            if ($num_date > $loan->penalty_period1) {
                                if ($sch->type == 'downpayment') {
                                    $sch_penalty += 0;
                                } else {
                                    if ($loan->loan_penalty_type == '$') {
                                        if($sch_monthly - $sch_other_fee > 0){
                                            $sch_penalty += $d_diff * $loan->penalty_rate1;
                                        }
                                    } elseif ($loan->loan_penalty_type == '%') {
                                        $sch_penalty += ($sch_monthly - $sch_other_fee) * $d_diff * $loan->penalty_rate1 / 100;
                                    } else {
                                        $sch_penalty += ($sch_monthly - $sch_other_fee) * $d_diff * $loan->penalty_rate1 / 100;
                                    }
                                }
                            }
                        }
                        $is_penalty_no[$sch->no] = $sch_penalty;
                    }
                }
                if ($sch_monthly == 0) {
                    $sch_monthly = $sch_interest + $sch_fee + $sch_other_fee + $sch_principal;
                    $num_date = date_dif($sch->schedule_date, $today, 1, false);
                    if($sch->status == 0){
                        if ($num_date > $loan->penalty_period1) {
                            $num_date = $num_date - $loan->penalty_period1;
                            if ($sch->type == 'downpayment') {
                                $sch_penalty += 0;
                            } else {
                                if ($loan->loan_penalty_type == '$') {
                                    if(($sch_monthly - $sch_other_fee) > 0){
                                        $sch_penalty += $num_date * $loan->penalty_rate1;
                                    }
                                    // var_dump($num_date * $loan->penalty_rate1);
                                    // var_dump($loan->penalty_rate1);
                                    // var_dump($num_date);
                                    // var_dump($sch_penalty);
                                } elseif ($loan->loan_penalty_type == '%') {
                                    $sch_penalty += ($sch_monthly - $sch_other_fee) * $num_date * $loan->penalty_rate1 / 100;
                                } else {
                                    $sch_penalty += ($sch_monthly - $sch_other_fee) * $num_date * $loan->penalty_rate1 / 100;
                                }
                            }
                        }
                    }
                }
                if (round($sch_monthly, 2) > 0 || round($sch_penalty, 2) > 0) {
                    // array_push($sch_repay_arr, [
                    //     'month_idx' => $sch->no,
                    //     'schedule_date' => $sch->schedule_date,
                    //     'interest' => round($sch_interest, 2),
                    //     'fee' => round($sch_fee, 2),
                    //     'other_fee' => round($sch_other_fee, 2),
                    //     'principal' => round($sch_principal, 2),
                    //     'penalty' => round($sch_penalty, 2),
                    //     'total' => round($sch_monthly, 2) + round($sch_penalty, 2),
                    //     'type' => $sch->type
                    // ]);

                    $sch_repay_arr[$sch->type][] = [
                        'loan_id'   => $loan->id,
                        'month_idx' => $sch->no,
                        'schedule_date' => $sch->schedule_date,
                        'interest' => round($sch_interest, 2),
                        'fee' => round($sch_fee, 2),
                        'other_fee' => round($sch_other_fee, 2),
                        'principal' => round($sch_principal, 2),
                        'penalty' => round($sch_penalty, 2),
                        'total' => round($sch_monthly, 2) + round($sch_penalty, 2),
                        'type' => $sch->type
                    ];
                }
            }
            //if($loan->id == 540) dd($sch_repay_arr);
            return $sch_repay_arr;
        }
    }

    if (!function_exists('get_auto_repay_array_migrate')) {
        function get_auto_repay_array_migrate($loan, $paydate = null,$sch_no = 0)
        {

            $payment = $loan->payment;
            is_null($paydate) ? $today = date('Y-m-d') : $today = date('Y-m-d', strtotime($paydate));
            $repayment_sch = RepaymentSchedule::where('loan_id',$loan->id)->where('no',$sch_no)->get();
            $schedule = schedule_sort_by_no($repayment_sch);
            $sch_repay_arr = [];
            $is_penalty_no = [];
            $month_idx = 0;
            $month_idx_till_today = get_month_idx($loan->stat_payment_date, $today, $loan->schedule);
            foreach ($schedule as $key => $sch) {
                if($sch->schedule_date > $today){
                    $today = date('Y-m-d',strtotime($sch->schedule_date.'+ 1 day'));
                }
                // if ($sch->no > $month_idx_till_today) break;
                $sch_interest = $sch->interest;
                $sch_fee = $sch->fee;
                $sch_other_fee = $sch->other_fee;
                $sch_principal = $sch->principal;

                $sch_penalty = 0;
                $sch_monthly = 0;
                foreach ($payment as $pay) {
                    if ($sch->no == $pay->payment_month) {
                        $sch_interest -= $pay->paid_interest;
                        $sch_fee -= $pay->paid_fee;
                        $sch_other_fee -= $pay->paid_other_fee;
                        $sch_principal -= $pay->paid_principal;
                        $sch_penalty = 0;
                        if ($pay->status == 0) { // if owed
                            $sch_monthly = $sch_interest + $sch_fee + $sch_other_fee + $sch_principal;
                            $sch_penalty = $pay->repayment_owed - $sch_monthly - $sch_other_fee;
                            $num_date = date_dif($sch->schedule_date, $today, 1, false);
                            $bd_diff = date_dif($sch->schedule_date, $pay->repayment_date, 1, false);
                            if ($bd_diff <= $loan->penalty_period1) {
                                $d_diff = date_dif($sch->schedule_date, $today, 1, false);
                            } else {
                                $d_diff = date_dif($pay->repayment_date, $today, 1, false);
                            }
                            if ($num_date > $loan->penalty_period1) {
                                if ($sch->type == 'downpayment') {
                                    $sch_penalty += 0;
                                } else {
                                    if ($loan->loan_penalty_type == '$') {
                                        if($sch_monthly - $sch_other_fee > 0){
                                            $sch_penalty += $d_diff * $loan->penalty_rate1;
                                        }
                                    } elseif ($loan->loan_penalty_type == '%') {
                                        $sch_penalty += ($sch_monthly - $sch_other_fee) * $d_diff * $loan->penalty_rate1 / 100;
                                    } else {
                                        $sch_penalty += ($sch_monthly - $sch_other_fee) * $d_diff * $loan->penalty_rate1 / 100;
                                    }
                                }
                            }
                        }
                        $is_penalty_no[$sch->no] = $sch_penalty;
                    }
                }
                if ($sch_monthly == 0) {
                    $sch_monthly = $sch_interest + $sch_fee + $sch_other_fee + $sch_principal;
                    $num_date = date_dif($sch->schedule_date, $today, 1, false);
                    if($sch->status == 0){
                        if ($num_date > $loan->penalty_period1) {
                            if ($sch->type == 'downpayment') {
                                $sch_penalty += 0;
                            } else {
                                if ($loan->loan_penalty_type == '$') {
                                    if(($sch_monthly - $sch_other_fee) > 0){
                                        $sch_penalty += $num_date * $loan->penalty_rate1;
                                    }
                                } elseif ($loan->loan_penalty_type == '%') {
                                    $sch_penalty += ($sch_monthly - $sch_other_fee) * $num_date * $loan->penalty_rate1 / 100;
                                } else {
                                    $sch_penalty += ($sch_monthly - $sch_other_fee) * $num_date * $loan->penalty_rate1 / 100;
                                }
                            }
                        }
                    }
                }
                if (round($sch_monthly, 4) > 0 || round($sch_penalty, 4) > 0) {
                    $sch_repay_arr[$sch->type][] = [
                        'loan_id'   => $loan->id,
                        'month_idx' => $sch->no,
                        'schedule_date' => $sch->schedule_date,
                        'interest' => round($sch_interest, 4),
                        'fee' => round($sch_fee, 4),
                        'other_fee' => round($sch_other_fee, 4),
                        'principal' => round($sch_principal, 4),
                        'penalty' => round($sch_penalty, 4),
                        'total' => round($sch_monthly, 4) + round($sch_penalty, 4),
                        'type' => $sch->type
                    ];
                }
            }
            return $sch_repay_arr;
        }
    }


    if (!function_exists('get_auto_repay_array')) {
        function get_auto_repay_array($loan, $paydate = null)
        {
            $payment = $loan->payment;
            //$schedule = $loan->schedule;
            is_null($paydate) ? $today = date('Y-m-d') : $today = date('Y-m-d', strtotime($paydate));
            $repayment_sch = RepaymentSchedule::where('loan_id',$loan->id)->where('schedule_date','<=',$paydate)->get();
            $schedule = schedule_sort_by_no($repayment_sch);
            $sch_repay_arr = [];
//            $month_idx = $loan->schedule[1]->no;
            $month_idx = 0;
            // $month_idx_till_today = get_month_idx($loan->disburse_date, $today, $loan->schedule);
            $month_idx_till_today = get_month_idx($loan->stat_payment_date, $today, $loan->schedule);
            foreach ($schedule as $key => $sch) {
                if ($sch->no > $month_idx_till_today) break;
                $sch_interest = $sch->interest;
                //if(count($schedule)-1 == $key){
                //    dd($loan->loan_duration);
                // if($loan->loan_duration == $key){
                //     $sch_interest = get_last_sch_int($loan->loan_account_id, $loan->transaction, $loan->interest_rate, $today);
                // }
                $sch_fee = $sch->fee;
                $sch_other_fee = $sch->other_fee;
                $sch_principal = $sch->principal;
                $sch_penalty = 0;
                $sch_monthly = 0;
                foreach ($payment as $pay) {
                    if ($sch->no == $pay->payment_month) {
                        $sch_interest -= $pay->paid_interest;
                        $sch_fee -= $pay->paid_fee;
                        $sch_other_fee -= $pay->paid_other_fee;
                        $sch_principal -= $pay->paid_principal;
                        $sch_penalty = 0;
                        if ($pay->status == 0) { // if owed
                            $sch_monthly = $sch_interest + $sch_fee + $sch_other_fee + $sch_principal;
                            $sch_penalty = $pay->repayment_owed - $sch_monthly - $sch_other_fee;
                            $num_date = date_dif($sch->schedule_date, $today, 1, false);
                            $bd_diff = date_dif($sch->schedule_date, $pay->repayment_date, 1, false);
                            if ($bd_diff <= $loan->penalty_period1) {
                                $d_diff = date_dif($sch->schedule_date, $today, 1, false);
                            } else {
                                $d_diff = date_dif($pay->repayment_date, $today, 1, false);
                            }
                            if ($num_date > $loan->penalty_period1) {
                                if ($sch->type == 'downpayment') {
                                    $sch_penalty += 0;
                                } else {
                                    if ($loan->loan_penalty_type == '$') {
                                        $sch_penalty += $d_diff * $loan->penalty_rate1;
                                    } elseif ($loan->loan_penalty_type == '%') {
                                        $sch_penalty += ($sch_monthly - $sch_other_fee) * $d_diff * $loan->penalty_rate1 / 100;
                                    } else {
                                        $sch_penalty += ($sch_monthly - $sch_other_fee) * $d_diff * $loan->penalty_rate1 / 100;
                                    }
                                }
                            }
                        }
                    }
                }
                if ($sch_monthly == 0) {
                    $sch_monthly = $sch_interest + $sch_fee + $sch_other_fee + $sch_principal;
                    $num_date = date_dif($sch->schedule_date, $today, 1, false);
                    if($sch->status == 0){
                        if ($num_date > $loan->penalty_period1) {
                            if ($sch->type == 'downpayment') {
                                $sch_penalty += 0;
                            } else {
                                if ($loan->loan_penalty_type == '$') {
                                    $sch_penalty += $num_date * $loan->penalty_rate1;
                                    // var_dump($num_date * $loan->penalty_rate1);
                                    // var_dump($loan->penalty_rate1);
                                    // var_dump($num_date);
                                    // var_dump($sch_penalty);
                                } elseif ($loan->loan_penalty_type == '%') {
                                    $sch_penalty += ($sch_monthly - $sch_other_fee) * $num_date * $loan->penalty_rate1 / 100;
                                } else {
                                    $sch_penalty += ($sch_monthly - $sch_other_fee) * $num_date * $loan->penalty_rate1 / 100;
                                }
                            }
                        }
                    }
                }
                // var_dump($sch_penalty);
                if (round($sch_monthly, 2) > 0 || round($sch_penalty, 2) > 0) {
                    array_push($sch_repay_arr, [
                        'month_idx' => $sch->no,
                        'schedule_date' => $sch->schedule_date,
                        'interest' => round($sch_interest, 2),
                        'fee' => round($sch_fee, 2),
                        'other_fee' => round($sch_other_fee, 2),
                        'principal' => round($sch_principal, 2),
                        'penalty' => round($sch_penalty, 2),
                        'total' => round($sch_monthly, 2) + round($sch_penalty, 2),
                        'type' => $sch->type
                    ]);
                }
            }
            //if($loan->id == 540) dd($sch_repay_arr);
            return $sch_repay_arr;
        }
    }

    if (!function_exists('get_auto_repay_array_new')) {
        function get_auto_repay_array_new($loan, $paydate = null)
        {
            $payment = $loan->payment;
            is_null($paydate) ? $today = date('Y-m-d') : $today = date('Y-m-d', strtotime($paydate));
            $repayment_sch = RepaymentSchedule::where('loan_id',$loan->id)->where('schedule_date','<=',$paydate)->get();
            $schedule = schedule_sort_by_no($repayment_sch);
            $sch_repay_arr = [];
            $month_idx = 0;
            $month_idx_till_today = get_month_idx($loan->stat_payment_date, $today, $loan->schedule);
            $penalty_days = 0;
            foreach ($schedule as $key => $sch) {
                if ($sch->no > $month_idx_till_today) break;
                $sch_interest = $sch->interest;
                $sch_fee = $sch->fee;
                $sch_other_fee = $sch->other_fee;
                $sch_principal = $sch->principal;
                $sch_penalty = 0;
                $sch_monthly = 0;
                $d_diff = 0;
                foreach ($payment as $pay) {
                    if ($sch->no == $pay->payment_month) {
                        $sch_interest -= $pay->paid_interest;
                        $sch_fee -= $pay->paid_fee;
                        $sch_other_fee -= $pay->paid_other_fee;
                        $sch_principal -= $pay->paid_principal;
                        $sch_penalty = 0;
                        if ($pay->status == 0) { // if owed
                            $sch_monthly = $sch_interest + $sch_fee + $sch_other_fee + $sch_principal;
                            $sch_penalty = $pay->repayment_owed - $sch_monthly - $sch_other_fee;
                            $num_date = date_dif($sch->schedule_date, $today, 1, false);
                            $bd_diff = date_dif($sch->schedule_date, $pay->repayment_date, 1, false);
                            if ($bd_diff <= $loan->penalty_period1) {
                                $d_diff = date_dif($sch->schedule_date, $today, 1, false);
                            } else {
                                $d_diff = date_dif($pay->repayment_date, $today, 1, false);
                            }
                            if ($num_date > $loan->penalty_period1) {
                                if ($sch->type == 'downpayment') {
                                    $sch_penalty += 0;
                                } else {
                                    if ($loan->loan_penalty_type == '$') {
                                        $sch_penalty += $d_diff * $loan->penalty_rate1;
                                    } elseif ($loan->loan_penalty_type == '%') {
                                        $sch_penalty += ($sch_monthly - $sch_other_fee) * $d_diff * $loan->penalty_rate1 / 100;
                                    } else {
                                        $sch_penalty += ($sch_monthly - $sch_other_fee) * $d_diff * $loan->penalty_rate1 / 100;
                                    }
                                }
                            }
                        }
                    }
                }
                if ($sch_monthly == 0) {
                    $sch_monthly = $sch_interest + $sch_fee + $sch_other_fee + $sch_principal;
                    $num_date = date_dif($sch->schedule_date, $today, 1, false);
                    $d_diff = $num_date;
                    if($sch->status == 0){ 
                        if ($num_date > $loan->penalty_period1) {
                            if ($sch->type == 'downpayment') {
                                $sch_penalty += 0;
                            } else {
                                if ($loan->loan_penalty_type == '$') {
                                    $sch_penalty += $num_date * $loan->penalty_rate1;
                                } elseif ($loan->loan_penalty_type == '%') {
                                    $sch_penalty += ($sch_monthly - $sch_other_fee) * $num_date * $loan->penalty_rate1 / 100;
                                } else {
                                    $sch_penalty += ($sch_monthly - $sch_other_fee) * $num_date * $loan->penalty_rate1 / 100;
                                }
                            }
                        }
                    }
                }
                if (round($sch_monthly, 2) > 0 || round($sch_penalty, 2) > 0) {
                    array_push($sch_repay_arr, [
                        'month_idx' => $sch->no,
                        'schedule_date' => $sch->schedule_date,
                        'interest' => round($sch_interest, 2),
                        'fee' => round($sch_fee, 2),
                        'other_fee' => round($sch_other_fee, 2),
                        'principal' => round($sch_principal, 2),
                        'penalty' => round($sch_penalty, 2),
                        'total' => round($sch_monthly, 2) + round($sch_penalty, 2),
                        'type' => $sch->type,
                        'd_diff' => $d_diff
                    ]);
                }
            }
            return $sch_repay_arr;
        }
    }

    if (!function_exists('get_restructure_balance')) {
        function get_restructure_balance($loan)
        {
            $payment = $loan->payment;
            $schedule = schedule_sort_by_no($loan->schedule);
            $sch_repay_arr = [];
            $month_idx = 0;
            $today = date('Y-m-d');
            $balance_downpayment = 0;
            $balance_loan = 0;
            $loan_paid_principal = 0;
            $loan_paid_downpayment = 0;
            $last_no_pay = '';
            $loan_no_type = '';
            $loan_no = '';
            $payment_arr = [];
            foreach ($payment as $pay_arr) {
                if (isset($payment_arr[$pay_arr->payment_month])) {
                    $payment_arr[$pay_arr->payment_month]['principal'] += $pay_arr->paid_principal;
                } else {
                    $payment_arr[$pay_arr->payment_month]['principal'] = $pay_arr->paid_principal;
                }
            }
            foreach ($schedule as $key => $sch) {
                $sch_principal = $sch->principal;
                if (isset($payment_arr[$sch->no])) {
                    if ($sch->type == 'downpayment') {
                        $balance_downpayment += ($sch_principal - $payment_arr[$sch->no]['principal']);
                        $loan_paid_downpayment += $payment_arr[$sch->no]['principal'];
                    } else {
                        $balance_loan += ($sch_principal - $payment_arr[$sch->no]['principal']);
                        $loan_paid_principal += $payment_arr[$sch->no]['principal'];
                    }
                    $last_no_pay = '';
                } else {
                    if ($last_no_pay == '') {
                        if ($sch->loan_no == 1) {
                            $loan_no = $sch->loan_no;
                        } else {
                            $loan_no = $sch->loan_no;
                        }
                        // var_dump($loan_no);
                        $last_no_pay = $sch->no;
                        $loan_no_type = $sch->type;
                    }
                    if ($sch->type == 'downpayment') {
                        $balance_downpayment += ($sch_principal - $payment_arr[$sch->no]['principal']);
                    } else {
                        $balance_loan += ($sch_principal - $payment_arr[$sch->no]['principal']);
                    }
                }

            }
            $sch_repay_arr = [
                'downpayment' => $balance_downpayment,
                'balance_loan' => $balance_loan,
                'last_no_pay' => $last_no_pay,
                'loan_no' => $loan_no,
                'loan_no_type' => $loan_no_type,
                'loan_paid_principal' => $loan_paid_principal,
                'loan_paid_downpayment' => $loan_paid_downpayment,
            ];
            return $sch_repay_arr;
        }
    }
    //@Chamroeun Record Transaction > Journal Requiery > Journal Detail
    // $journal_arr = [debit_id, debit_amount, debit_desc, credit_id, credit_amount, credit_desc, journal_desc]
    if (!function_exists('record_journal_no_trans')) {
        function record_journal_no_trans($loan_account = null, $record_date, $journal_arr = [], $branch_id, $user_id, $is_audit = 0, $ex_journal_id = null, $is_receipt = 0)
        {
            // Journal Requiry
            if (is_null($ex_journal_id)) {
                if ($is_receipt == 1) {
                    $branch_code = CompanyBranch::select('branch_code')->where('id', '=', $branch_id)->first()->branch_code;
                    $last = isset(JournalRequiry::where('years', date('Y'))->orderBy('no', 'DESC')->first()->no) ? JournalRequiry::where('years', date('Y'))->orderBy('no', 'DESC')->first()->no : null;
                    if (!$last) {
                        $last = 0;
                    }
                    $last = (int)filter_var($last, FILTER_SANITIZE_NUMBER_INT);
                    $no = $last + 1;
                    $receipt_no = 'RP' . $branch_code . date('y') . str_pad($no, 5, '0', STR_PAD_LEFT);
                    $journal = new JournalRequiry;
                    $journal->years = date('Y');
                    $journal->no = $no;
                    $journal->receipt_no = $receipt_no;
                    $journal->trans_type = isset($journal_arr[0][7]) ? $journal_arr[0][7] : '';                   
                    $journal->entry_date = $record_date;
                    $journal->description = $journal_arr[0][6];
                    $journal->user_id = $user_id;
                    $journal->is_audit = $is_audit;
                } else {
                    $journal = new JournalRequiry;
                    $journal->entry_date = $record_date;
                    $journal->description = $journal_arr[0][6];
                    $journal->user_id = $user_id;
                    $journal->is_audit = $is_audit;
                }
                if (!$journal->save()) {
                    return redirect('/');
                }
            }
            // Journal Detail
            for ($i = 0; $i < count($journal_arr); $i++) {

                // Journal Detail(Debit)
                $jd = new JournalDetail;
                $jd->journal_id = is_null($ex_journal_id) ? $journal->id : $ex_journal_id;
                $jd->coa_id = $journal_arr[$i][0];
                $jd->reference = is_null($loan_account) ? '' : $loan_account->loan_ref;
                $jd->branch_code = CompanyBranch::select('branch_code')->where('id', '=', $branch_id)->first()->branch_code;
                $prev_bl = array_fill(0, 2, 0.0);
                $prev_row = JournalDetail::select('b_debit', 'b_credit')
                    ->where('coa_id', $jd->coa_id)
                    ->orderBy('id', 'desc')
                    ->first();
                if (!empty($prev_row)) {
                    $prev_bl[0] = $prev_row->b_debit;
                    $prev_bl[1] = $prev_row->b_credit;
                }
                $jd->p_debit = $prev_bl[0];
                $jd->p_credit = $prev_bl[1];
                $jd->debit = $journal_arr[$i][1];
                $jd->credit = 0;
                $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit);
                $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                $jd->description = $journal_arr[$i][2];
                $jd->is_audit = $is_audit;
                if (!$jd->save()) {
                    $jd->delete();
                    $journal->delete();
                    return redirect('/');
                }
                // Journal Detail(Credit)
                $jd = new JournalDetail;
                $jd->journal_id = is_null($ex_journal_id) ? $journal->id : $ex_journal_id;
                $jd->coa_id = $journal_arr[$i][3];
                $jd->reference = is_null($loan_account) ? '' : $loan_account->loan_ref;
                $jd->branch_code = CompanyBranch::select('branch_code')->where('id', '=', $branch_id)->first()->branch_code;
                $prev_bl = array_fill(0, 2, 0.0);
                $prev_row = JournalDetail::select('b_debit', 'b_credit')
                    ->where('coa_id', $jd->coa_id)
                    ->orderBy('id', 'desc')
                    ->first();
                if (!empty($prev_row)) {
                    $prev_bl[0] = $prev_row->b_debit;
                    $prev_bl[1] = $prev_row->b_credit;
                }
                $jd->p_debit = $prev_bl[0];
                $jd->p_credit = $prev_bl[1];
                $jd->debit = 0;
                $jd->credit = $journal_arr[$i][4];
                $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit);
                $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                $jd->description = $journal_arr[$i][5];
                $jd->is_audit = $is_audit;
                if (!$jd->save()) {
                    $jd->delete();
                    $journal->delete();
                    return redirect('/');
                }
            }
            return array('journal_id' => is_null($ex_journal_id) ? $journal->id : $ex_journal_id, 'journal_detail_id' => $jd->id);

        }
    }

    if (!function_exists('record_journal_resschedule')) {
        function record_journal_resschedule($loan, $record_date, $type = "", $data = null, $journal_arr = [], $branch_id, $user_id, $invoice = null)
        {

        }
    }

    //@Chamroeun Record Transaction > Journal Requiery > Journal Detail
    // $journal_arr = [debit_id, debit_amount, debit_desc, credit_id, credit_amount, credit_desc, journal_desc]
    if (!function_exists('record_journal')) {
        function record_journal($loan, $record_date, $type = "", $data = null, $journal_arr = [], $branch_id, $user_id, $invoice = null, $loan_repayment_type = null)
        {
            switch ($type) {
                case "Auto Loan Repayment":
                case "Loan Repayment":
                case "Close Loan":
                case "Reschedule Loan":
                case "Fee Charge":
                case "Write-Off Loan":
                case "Terminate Loan":
                {
                    if ($type == "Loan Repayment" || $type == "Auto Loan Repayment" || $type == 'Reschedule Loan') {
                        $act_int = $data['act_interest'];
                        $act_prin = $data['act_principal'];
                        $act_fee = $data['act_fee'];
                        $act_other_fee = $data['act_other_fee'];
                        $act_penalty = $data['act_penalty'];
                        $act_amount = $data['act_total'];
                    } elseif ($type == "Fee Charge") {
                        $act_int = 0;
                        $act_prin = 0;
                        $act_fee = $data['charge_amount'];
                        $act_other_fee = 0;
                        $act_penalty = 0;
                        $act_amount = $data['charge_amount'];
                    } elseif ($type == "Close Loan") {
                        $act_int = $data['air_amount'];
                        $act_prin = $data['prin_amount'];
                        $act_fee = $data['fee_amount'];
                        $act_other_fee = (is_null($data['fee_other_amount'])) ? 0 : $data['fee_other_amount'];
                        $act_penalty = $data['penalty'];
                        $act_amount = $data['total_amount'];
                    } elseif ($type == "Write-Off Loan") {
                        $act_int = $data['air_amount'];
                        $act_prin = $data['prin_amount'];
                        $act_fee = $data['fee_amount'];
                        $act_other_fee = $data['other_fee_amount'];
                        $act_penalty = $data['penalty'];
                        $act_amount = $data['total_amount'];
                    } elseif ($type == "Terminate Loan") {
                        $act_int = $data['air_amount'];
                        $act_prin = $data['prin_amount'];
                        $act_fee = $data['fee_amount'];
                        $act_other_fee = $data['other_fee_amount'];
                        $act_penalty = $data['penalty'];
                        $act_amount = $data['total_amount'];
                    } else {
                        $act_int = 0;
                        $act_prin = $data['amount'];
                        $act_fee = 0;
                        $act_other_fee = 0;
                        $act_penalty = 0;
                        $act_amount = $data['amount'];
                    }
                    // Transaction
                    $last_transaction = TransactionsRequiry::select('id', 'balance')->where('loan_id', $loan->id)->orderBy('id', 'DESC')->first();
                    $transaction = new TransactionsRequiry();
                    $transaction->loan_id = $loan->id;
                    $transaction->trans_date = $record_date;
                    $transaction->trans_type = $type;
                    $transaction->description = $data['note'];
                    $transaction->amount = $act_amount;
                    $transaction->interest = $act_int;
                    $transaction->fee = $act_fee;
                    $transaction->other_fee = $act_other_fee;
                    $transaction->penalty = $act_penalty;
                    $transaction->principal = $act_prin;
                    $transaction->loan_repayment_type = $loan_repayment_type;
                    //$transaction->balance = $last_transaction->balance - $act_prin;
                    if($loan_repayment_type == 'downpayment'){
                        $transaction->balance_downpayment = $loan->client_loan_account->balance_downpayment - $act_prin;
                    }else{
                        $transaction->balance = $loan->client_loan_account->balance - $act_prin;
                    }

                    $transaction->user_id = $user_id;
                    $transaction->flag = 1;
                    $transaction->is_audit = 1;
                    if ($transaction->save()) {
                        // Journal Requiry
                        $journal = new JournalRequiry;
                        $journal->tran_id = $transaction->id;
                        $journal->entry_date = $record_date;
                        if ($type == "Fee Charge"){
                            $journal->receipt_no = $data['be_cash_receipt_no'];
                        }
                        $journal->description = $data['note'];
                        $journal->user_id = $user_id;
                        $journal->trans_type = $type;
                        $journal->is_audit = 1;
                        if ($journal->saveWithGeneratedInvoice()) {
                            $ex_db_coa_id = null;
                            for ($i = 0; $i < count($journal_arr); $i++) {
                                // In case of Auto Repayment
                                // if($type ==  "Auto Loan Repayment"){
                                //     // Journal Detail(Debit)
                                //     $jd = new JournalDetail;
                                // }else{
                                // Journal Detail
                                // Journal Detail(Debit)
                                //var_dump($journal_arr[$i][0]); dd($ex_db_coa_id);
                                if ($journal_arr[$i][0] != $ex_db_coa_id) { // new DB COA
                                    $jd_db = new JournalDetail;
                                    $jd_db->debit = $journal_arr[$i][1];
                                    $jd_db->description = $journal_arr[$i][2];
                                    $jd_db->journal_id = $journal->id;
                                    $jd_db->coa_id = $journal_arr[$i][0];
                                    $ex_db_coa_id = $journal_arr[$i][0];
                                    $jd_db->reference = $loan->contract_id;
                                    $jd_db->branch_code = CompanyBranch::select('branch_code')->where('id', '=', $branch_id)->first()->branch_code;
                                    $prev_bl = array_fill(0, 2, 0.0);
                                    $prev_row = JournalDetail::select('b_debit', 'b_credit')
                                        ->where('coa_id', $jd_db->coa_id)
                                        ->orderBy('id', 'desc')
                                        ->first();
                                    if (!empty($prev_row)) {
                                        $prev_bl[0] = $prev_row->b_debit;
                                        $prev_bl[1] = $prev_row->b_credit;
                                    }
                                    $jd_db->p_debit = $prev_bl[0];
                                    $jd_db->p_credit = $prev_bl[1];
                                    //$jd->debit = $journal_arr[$i][1];
                                    $jd_db->credit = 0;
                                    $jd_db->b_debit = floatval($prev_bl[0]) + floatval($jd_db->debit);
                                    $jd_db->b_credit = floatval($prev_bl[1]) + floatval($jd_db->credit);
                                    $jd_db->is_audit = 1;
                                } else { // the same DB COA
                                    $jd_db->debit += $journal_arr[$i][1];
                                    $jd_db->b_debit = floatval($jd_db->p_debit) + floatval($jd_db->debit);
                                    $jd_db->description = $journal_arr[$i][6];
                                }
                                if ($jd_db->save()) {
                                } else {
                                    $jd_db->delete();
                                    $journal->delete();
                                    $transaction->delete();
                                    return redirect('/');
                                }
                                //}
                                // Journal Detail(Credit)
                                $jd = new JournalDetail;
                                $jd->journal_id = $journal->id;
                                $jd->coa_id = $journal_arr[$i][3];
                                $jd->reference = $loan->contract_id;
                                $jd->branch_code = CompanyBranch::select('branch_code')->where('id', '=', $branch_id)->first()->branch_code;
                                $prev_bl = array_fill(0, 2, 0.0);
                                $prev_row = JournalDetail::select('b_debit', 'b_credit')
                                    ->where('coa_id', $jd->coa_id)
                                    ->orderBy('id', 'desc')
                                    ->first();
                                if (!empty($prev_row)) {
                                    $prev_bl[0] = $prev_row->b_debit;
                                    $prev_bl[1] = $prev_row->b_credit;
                                }
                                $jd->p_debit = $prev_bl[0];
                                $jd->p_credit = $prev_bl[1];
                                $jd->debit = 0;
                                $jd->credit = $journal_arr[$i][4];
                                $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit);
                                $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                                $jd->description = $journal_arr[$i][5];
                                $jd->is_audit = 1;
                                if ($jd->save()) {
                                } else {
                                    $jd->delete();
                                    $journal->delete();
                                    $transaction->delete();
                                    return redirect('/');
                                }

                                // Transaction status update (0 -> 1)
                                $tran = TransactionsRequiry::where('id', '=', $transaction->id)->where('flag', '=', 0)->first();
                                if (!empty($tran)) {
                                    $tran->flag = 1;
                                    $tran->invoice_number = str_pad($journal->id, 8, '0', STR_PAD_LEFT);;
                                    $tran->save();
                                    //update client_loan_accounts balance
                                    $clientLoanAccount = $loan->client_loan_account;
                                    $clientLoanAccount->balance = $transaction->balance;
                                    if ($type == "Close Loan") $clientLoanAccount->status = 8; //closed
                                    $clientLoanAccount->save();
                                }
                            }
                        } else {
                            $transaction->delete();
                            return redirect('/');
                        }

                    }
                    break;
                }
                case "Auto Accrued Interest":
                {
                    /*
                    $transaction = new TransactionsRequiry();
                    $transaction->id = TransactionsRequiry::max('id') + 1;
                    $transaction->loan_id = $loan->id;
                    $transaction->trans_date = $record_date;
                    $transaction->trans_type = $type;
                    $transaction->amount = $journal_arr[0][1];
                    $transaction->description = $transaction->trans_type . " ( " . $loan->contract_id . " )";
                    $transaction->balance = $loan->transaction_req->first()->balance;
                    $transaction->user_id = $user_id;
                    $transaction->flag = 1;
                    $transaction->is_audit = 1;
                    if($transaction->save()) {
                    */
                    // Journal Entry
                    $journal = new JournalRequiry;
                    //$journal->tran_id = $transaction->id;
                    $journal->entry_date = $record_date;
                    $journal->description = "Auto Accrued Interest" . "-" . $loan->contract_id;
                    $journal->user_id = $user_id;
                    $journal->is_audit = 1;
                    if ($journal->save()) {

                        for ($i = 0; $i < count($journal_arr); $i++) {
                            // Journal Detail(Debit)
                            $jd = new JournalDetail;
                            $jd->journal_id = $journal->id;
                            $jd->coa_id = $journal_arr[$i][0];
                            $jd->reference = $loan->contract_id;
                            $jd->branch_code = CompanyBranch::select('branch_code')->where('id', '=', $branch_id)->first()->branch_code;
                            $prev_bl = array_fill(0, 2, 0.0);
                            $prev_row = JournalDetail::select('b_debit', 'b_credit')
                                ->where('coa_id', $jd->coa_id)
                                ->orderBy('id', 'desc')
                                ->first();
                            if (!empty($prev_row)) {
                                $prev_bl[0] = $prev_row->b_debit;
                                $prev_bl[1] = $prev_row->b_credit;
                            }
                            $jd->p_debit = $prev_bl[0];
                            $jd->p_credit = $prev_bl[1];
                            $jd->debit = $journal_arr[$i][1];
                            $jd->credit = 0;
                            $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit);
                            $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                            $jd->description = $journal->description;
                            $jd->is_audit = 1;
                            if ($jd->save()) {
                            } else {
                                $jd->delete();
                                $journal->delete();
                                //$transaction->delete();
                                return redirect('/');
                            }

                            // Journal Detail(Credit)
                            $jd = new JournalDetail;
                            $jd->journal_id = $journal->id;
                            $jd->coa_id = $journal_arr[$i][3];
                            $jd->reference = $loan->contract_id;
                            $jd->branch_code = CompanyBranch::select('branch_code')->where('id', '=', $branch_id)->first()->branch_code;
                            $prev_bl = array_fill(0, 2, 0.0);
                            $prev_row = JournalDetail::select('b_debit', 'b_credit')
                                ->where('coa_id', $jd->coa_id)
                                ->orderBy('id', 'desc')
                                ->first();
                            if (!empty($prev_row)) {
                                $prev_bl[0] = $prev_row->b_debit;
                                $prev_bl[1] = $prev_row->b_credit;
                            }
                            $jd->p_debit = $prev_bl[0];
                            $jd->p_credit = $prev_bl[1];
                            $jd->debit = 0;
                            $jd->credit = $journal_arr[$i][4];
                            $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit);
                            $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                            $jd->description = $journal->description;
                            $jd->is_audit = 1;
                            if ($jd->save()) {
                            } else {
                                $jd->delete();
                                $journal->delete();
                                //$transaction->delete();
                                return redirect('/');
                            }
                        }
                    } else {
                        $journal->delete();
                        //$transaction->delete();
                        return redirect('/');
                    }

                    break;
                }
                case "WriteOff Payment":
                {
                    if (count($journal_arr) <= 0) {
                        return redirect('/');
                    }
                    // Journal Entry
                    $journal = new JournalRequiry;
                    //$journal->tran_id = $transaction->id;
                    $journal->entry_date = $record_date;
                    $journal->description = $journal_arr[0][6];
                    $journal->user_id = $user_id;
                    $journal->is_audit = 1;
                    if ($journal->save()) {

                        for ($i = 0; $i < count($journal_arr); $i++) {
                            // Journal Detail(Debit)
                            $jd = new JournalDetail;
                            $jd->journal_id = $journal->id;
                            $jd->coa_id = $journal_arr[$i][0];
                            $jd->reference = $loan->contract_id;
                            $jd->branch_code = CompanyBranch::select('branch_code')->where('id', '=', $branch_id)->first()->branch_code;
                            $prev_bl = array_fill(0, 2, 0.0);
                            $prev_row = JournalDetail::select('b_debit', 'b_credit')
                                ->where('coa_id', $jd->coa_id)
                                ->orderBy('id', 'desc')
                                ->first();
                            if (!empty($prev_row)) {
                                $prev_bl[0] = $prev_row->b_debit;
                                $prev_bl[1] = $prev_row->b_credit;
                            }
                            $jd->p_debit = $prev_bl[0];
                            $jd->p_credit = $prev_bl[1];
                            $jd->debit = $journal_arr[$i][1];
                            $jd->credit = 0;
                            $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit);
                            $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                            $jd->description = $journal->description;
                            $jd->is_audit = 1;
                            if ($jd->save()) {
                            } else {
                                $jd->delete();
                                $journal->delete();
                                //$transaction->delete();
                                return redirect('/');
                            }

                            // Journal Detail(Credit)
                            $jd = new JournalDetail;
                            $jd->journal_id = $journal->id;
                            $jd->coa_id = $journal_arr[$i][3];
                            $jd->reference = $loan->contract_id;
                            $jd->branch_code = CompanyBranch::select('branch_code')->where('id', '=', $branch_id)->first()->branch_code;
                            $prev_bl = array_fill(0, 2, 0.0);
                            $prev_row = JournalDetail::select('b_debit', 'b_credit')
                                ->where('coa_id', $jd->coa_id)
                                ->orderBy('id', 'desc')
                                ->first();
                            if (!empty($prev_row)) {
                                $prev_bl[0] = $prev_row->b_debit;
                                $prev_bl[1] = $prev_row->b_credit;
                            }
                            $jd->p_debit = $prev_bl[0];
                            $jd->p_credit = $prev_bl[1];
                            $jd->debit = 0;
                            $jd->credit = $journal_arr[$i][4];
                            $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit);
                            $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                            $jd->description = $journal->description;
                            $jd->is_audit = 1;
                            if ($jd->save()) {
                            } else {
                                $jd->delete();
                                $journal->delete();
                                //$transaction->delete();
                                return redirect('/');
                            }
                        }
                    } else {
                        $journal->delete();
                        //$transaction->delete();
                        return redirect('/');
                    }

                    break;
                }
                case "Register Fixed Asset":
                case "Adjust Fixed Asset":
                case "Accumulated Fixed Asset Depreciation":
                {
                    // Journal Requiry
                    $journal = new JournalRequiry;
                    $journal->entry_date = $record_date;
                    if (!is_null($invoice)) $journal->invoice_number = str_pad($invoice, 8, '0', STR_PAD_LEFT);
                    $journal->description = $journal_arr[0][6];
                    $journal->user_id = $user_id;
                    $journal->is_audit = 1;
                    if ($journal->save()) {
                        for ($i = 0; $i < count($journal_arr); $i++) {
                            // Journal Detail
                            // Journal Detail(Debit)
                            $jd = new JournalDetail;
                            $jd->journal_id = $journal->id;
                            $jd->coa_id = $journal_arr[$i][0];
                            $jd->branch_code = CompanyBranch::select('branch_code')->where('id', '=', $branch_id)->first()->branch_code;
                            $prev_bl = array_fill(0, 2, 0.0);
                            $prev_row = JournalDetail::select('b_debit', 'b_credit')
                                ->where('coa_id', $jd->coa_id)
                                ->orderBy('id', 'desc')
                                ->first();
                            if (!empty($prev_row)) {
                                $prev_bl[0] = $prev_row->b_debit;
                                $prev_bl[1] = $prev_row->b_credit;
                            }
                            $jd->p_debit = $prev_bl[0];
                            $jd->p_credit = $prev_bl[1];
                            $jd->debit = $journal_arr[$i][1];
                            $jd->credit = 0;
                            $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit);
                            $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                            $jd->description = $journal_arr[$i][2];
                            $jd->is_audit = 1;
                            if ($jd->save()) {
                            } else {
                                $jd->delete();
                                $journal->delete();
                                return redirect('/');
                            }
                            // Journal Detail(Credit)
                            $jd = new JournalDetail;
                            $jd->journal_id = $journal->id;
                            $jd->coa_id = $journal_arr[$i][3];
                            $jd->branch_code = CompanyBranch::select('branch_code')->where('id', '=', $branch_id)->first()->branch_code;
                            $prev_bl = array_fill(0, 2, 0.0);
                            $prev_row = JournalDetail::select('b_debit', 'b_credit')
                                ->where('coa_id', $jd->coa_id)
                                ->orderBy('id', 'desc')
                                ->first();
                            if (!empty($prev_row)) {
                                $prev_bl[0] = $prev_row->b_debit;
                                $prev_bl[1] = $prev_row->b_credit;
                            }
                            $jd->p_debit = $prev_bl[0];
                            $jd->p_credit = $prev_bl[1];
                            $jd->debit = 0;
                            $jd->credit = $journal_arr[$i][4];
                            $jd->b_debit = floatval($prev_bl[0]) + floatval($jd->debit);
                            $jd->b_credit = floatval($prev_bl[1]) + floatval($jd->credit);
                            $jd->description = $journal_arr[$i][5];
                            $jd->is_audit = 1;
                            if ($jd->save()) {
                            } else {
                                $jd->delete();
                                $journal->delete();
                                return redirect('/');
                            }
                        }
                    } else {
                        return redirect('/');
                    }

                    break;
                }
                default:
                {
                    break;
                }

            }

        }
    }


    if (!function_exists('getFooter')) {
        /**
         * @param
         * @param
         * @return
         */
        function getFooter($boo = null)
        {
            if ($boo) {
                $cl = 'div3';
            } else {
                $cl = 'div2';
            }

            $output = '<div class="prepare">';
            $output .= '<div class="' . $cl . '">';
            $output .= '<p class="bolder">Prepared by :</p>';
            $output .= '<p>Name:</p>';
            $output .= '<p>Date............/............./.....................</p>';
            $output .= '</div>';

            $output .= '<div class="' . $cl . '">';
            $output .= '<p class="bolder">Verified by :</p>';
            $output .= '<p>Name:</p>';
            $output .= '<p>Date............/............./.....................</p>';
            $output .= '</div>';

            if ($boo) {
                $output .= '<div class="' . $cl . '">';
                $output .= '<p class="bolder">Approved by :</p>';
                $output .= '<p>Name:</p>';
                $output .= '<p>Date............/............./.....................</p>';
                $output .= '</div>';
            }
            $output .= '</div>';

            return $output;
        }
    }
    if (!function_exists('getLastAccruedDate')) {
        function getLastAccruedDate($accrued_journal_detail, $start_date, $transfer_date = "0000-00-00")
        {
            $entry_date = date('Y-m-d', strtotime($start_date));
            if ($transfer_date != "0000-00-00" && $transfer_date != null) {
                $entry_date = date('Y-m-d', strtotime($transfer_date));
            }
            $date_arr = [];
            if (!is_null($accrued_journal_detail)) {
                foreach ($accrued_journal_detail as $jd) {
                    if ($jd->debit > 0 && (stristr($jd->description, 'Auto Accrued Interest') !== FALSE || (stristr($jd->description, 'provision') !== FALSE))) {
                        array_push($date_arr, date('Y-m-d', strtotime($jd->entry_date)));
                    }
                }
            }
            usort($date_arr, "strcmp");
            if (sizeof($date_arr) > 0) {
                //dd($date_arr[0]);
                if (date_dif($date_arr[count($date_arr) - 1], $entry_date, 1, false) < 0) {
                    return $date_arr[count($date_arr) - 1];
                } else {
                    return $entry_date;
                }
            } else {
                return $entry_date;
            }

        }
    }

}
if (!function_exists('getLastAccruedDateNew')) {
    function getLastAccruedDateNew($accrued_journal_detail, $start_date)
    {
        $entry_date = date('Y-m-d', strtotime($start_date));
        $date_arr = [];
        if (!is_null($accrued_journal_detail)) {
            foreach ($accrued_journal_detail as $jd) {
                if ($jd->debit > 0 && stristr($jd->description, 'Auto Accrued Interest') !== FALSE) {
                    array_push($date_arr, $jd->journal->entry_date);

                }
            }
        }
        usort($date_arr, "strcmp");
        if (date_dif($date_arr[count($date_arr) - 1], $entry_date, 1, false) < 0) {
            return $date_arr[count($date_arr) - 1];
        } else {
            return $entry_date;
        }

    }
}
// if( !function_exists('route')){
//     function route($name, $parameters = array(), $absolute = true, $route = null){
//         route($name,$parameters,$absolute,$route);
//     }
//}

if (!function_exists('display_name')) {
    function display_name($user_id)
    {
        $user = User::find($user_id);
        return $user->name;
    }
}

if (!function_exists('number_format_acc')) {
    function number_format_acc($num)
    {
        return number_format($num, 2, '.', ',');
    }
}

if (!function_exists('calculateRepaySchedule')) {
    function calculateRepaySchedule($loan, $dpDate = null, $key = null)
    {

        if (is_null($dpDate)) $dpDate = date('Y-m-d');
        if ($loan) {
            $result = [];
            $sum = 0;
            $outstanding_amount = 0;
            $last_pay_date = 0;
            $each_date = 0;
            $overdue = 0;
            $lastDate = null;

            $current_date = date('Y-m-d H:i:s');
            $repayment_date = $loan->payment;
            $lastDate = $loan->payment->last()->repayment_date;
            $result["lastDate"] = $lastDate;
            //$sum = 0;
            foreach ($loan->payment as $p) {
                $sum += $p->paid_principal;
            }
            $result["total_paid_prin"] = $sum;

            $penalty_arr = LoanCalculate::getTotalPenalty($loan, $dpDate);
            $result["overdue"] = $penalty_arr[2];
            //dd($penalty_arr);
            $result["last_pay_date"] = $penalty_arr[3];
            $result["penalty_amount"] = round($penalty_arr[4] * 100) / 100;

            $result["next_sch_date"] = $penalty_arr[5];

            $schedule_principal = 0.0;
            $schedule_arr_result = get_sch_principal_array($loan, $dpDate);
            foreach ($schedule_arr_result as $sch_prin) {
                $schedule_principal += $sch_prin[1];
            }
            $result["schedule_principal"] = $schedule_principal;
            $schedule_fee = 0.0;
            $sch_fee_result = get_sch_fee_array($loan, $dpDate);
            foreach ($sch_fee_result as $sch_fee) {
                $schedule_fee += $sch_fee[1];
            }
            $result["schedule_fee"] = $schedule_fee;
            $schedule_interest = 0;
            //$last_paid_date = $loan->transaction[count($loan->transaction) - 1]->trans_date;
            $sch_interest_arr = get_sch_interest_array($loan->disburse_date, $loan->schedule, $loan, 1, $dpDate, $key); // ceil_flag = 1
            foreach ($sch_interest_arr as $sh_int) {
                $schedule_interest += $sh_int[3];
            }
            $result["schedule_interest"] = $schedule_interest;
            $trans_interest = 0.0;
            foreach ($loan->transaction as $tr) {
                $trans_interest += $tr->interest;
            }
            $result["trans_interest"] = $trans_interest;
        }
        return $result;
    }

    if (!function_exists('journalCalculate')) {
        function journalCalculate($client_loan_acc, $start_date = null, $end_date = null, $keyword = "air")
        {
            // prevent script's timeout
            set_time_limit(0);

            $pre_result = [];
            $cur_result = [];
            $dc_result = [];
            $last_acc_date = [];
            $start_date = ($start_date == "") ? date('Y-m-d', strtotime("2016-01-01")) : date('Y-m-d', strtotime($start_date));
            $end_date = ($end_date == "") ? date('Y-m-d', strtotime('+1 days')) : date('Y-m-d', strtotime($end_date . '+1 days'));
            $test = [];

            foreach ($client_loan_acc as $r) {
                $pre_tmp = [];
                $cur_tmp = [];
                $pre_tmp['date'] = 0;
                $pre_tmp['debit'] = 0;
                $pre_tmp['credit'] = 0;
                $cur_tmp['date'] = 0;
                $cur_tmp['debit'] = 0;
                $cur_tmp['credit'] = 0;

                // factorize this block
                if ($keyword == "air") {
                    $type = $r->journal_detail_air;
                } else {
                    $type = $r->journal_detail_coa;
                }

                foreach ($type as $d) {
                    if (is_null($d->journal)) continue;
                    $entry_date = date('Y-m-d', strtotime($d->journal->entry_date));
                    if (date_dif($start_date, $entry_date, 1, false) < 0) {
                        $pre_tmp['date'] = $entry_date;
                        $pre_tmp['debit'] += $d->debit;
                        $pre_tmp['credit'] += $d->credit;
                    } elseif (date_dif($end_date, $entry_date, 1, false) < 0) {
                        $cur_tmp['date'] = $entry_date;
                        $cur_tmp['debit'] += $d->debit;
                        $cur_tmp['credit'] += $d->credit;
                    }
                }
                if (($pre_tmp['debit'] + $pre_tmp['credit'] + $cur_tmp['debit'] + $cur_tmp['credit']) == 0) continue;
                $pre_result[$r->id] = $pre_tmp;
                $cur_result[$r->id] = $cur_tmp;
                $last_acc_date[$r->id] = getLastAccruedDateNew($type, $r->loan->disburse_date);
            }

            $dc_result = ['pre' => $pre_result, 'cur' => $cur_result, 'last_acc_date' => $last_acc_date];
            return $dc_result;
        }
    }
    if (!function_exists('journalCalculate_new')) {
        function journalCalculate_new($client_loan_acc, $start_date = null, $end_date = null, $keyword = "air")
        {
            // prevent script's timeout
            set_time_limit(0);

            $pre_result = [];
            $cur_result = [];
            $dc_result = [];
            $last_acc_date = [];
            $start_date = ($start_date == "") ? date('Y-m-d', strtotime("2016-01-01")) : date('Y-m-d', strtotime($start_date));
            $end_date = ($end_date == "") ? date('Y-m-d', strtotime('+1 days')) : date('Y-m-d', strtotime($end_date . '+1 days'));
            $test = [];
            foreach ($client_loan_acc as $r) {
                $jd_arr = [];
                // factorize this block
                if ($keyword == "air") {
                    $jd_arr = get_journal_bal_bal($r->air_id, $start_date, $end_date);

                } else {
                    $jd_arr = get_journal_bal_bal($r->coa_id, $start_date, $end_date);
                }
                if ($r->id == 47) dd($r);
                if (($jd_arr['st_debit_bal'] + $jd_arr['st_credit_bal'] + $jd_arr['cur_to_debit'] + $jd_arr['cur_to_credit']) == 0) continue;
                $pre_result[$r->id] = ['debit' => $jd_arr['st_debit_bal'], 'credit' => $jd_arr['st_credit_bal']];
                $cur_result[$r->id] = ['debit' => $jd_arr['cur_to_debit'], 'credit' => $jd_arr['cur_to_credit']];
                $last_acc_date[$r->id] = $jd_arr['last_acc_date'];
            }

            $dc_result = ['pre' => $pre_result, 'cur' => $cur_result, 'last_acc_date' => $last_acc_date];
            return $dc_result;
        }
    }
    /*
        if(!function_exists('getCoaBalance')) {
            function getCoaBalance($preJournalDetail, $curJournalDetail, $data = null, )
            {
              define('USDTOKHR', 4100);
              define('KHRM', 1000000);
              if ($data['start_date'] || $data['end_date']){
                // start < tr < end
                if ($data['start_date'] ) $curJournalDetail = $curJournalDetail->where('entry_date', '>=', $data['start_date']);
                if ($data['end_date']) $curJournalDetail = $curJournalDetail->where('entry_date', '<=', $data['end_date']);
                //$curJournalDetail = $curJournalDetail->get();
                // tr < start
                if ($data['end_date']) $preJournalDetail = $preJournalDetail->where('entry_date', '<', $data['start_date']);
              }
              $preJournalDetail = $preJournalDetail->get();
              $curJournalDetail = $curJournalDetail->get();

              $pre_balance = [];
              $pre_balance_val = 0;
              $coa_details = [];
              $coa_all = CoaCategory::orderBy('id', 'DESC')->get();
              $coa_all_nor = CoaCategory::orderBy('id', 'ASC')->get();

              if(count($preJournalDetail) > 0){
                  $cnt = 0; $old_coa = 0; $new_coa = 0;
                  foreach($preJournalDetail as $pre){
                    $new_coa = $pre->coa_id;
                    if($cnt == 0 && $new_coa != $old_coa){
                      $coa_details[$pre->coa_id]["pre_debit"] = 0;
                      $coa_details[$pre->coa_id]["pre_credit"] = 0;
                    }

                    if($data['consolidate'] == 1){
                      if($data['exhange_rate' == 1]){//if USD change to KHR mill....
                        if($pre['currency'] == 2 ){
                          $coa_details[$pre->coa_id]["pre_debit"] += $pre->debit * $rate;
                          $coa_details[$pre->coa_id]["pre_credit"] += $pre->credit * $rate;
                        }elseif($pre->currency == 1){//if KHR change to USD
                          $coa_details[$pre->coa_id]["pre_debit"] += $pre->debit / KHRM;
                          $coa_details[$pre->coa_id]["pre_credit"] += $pre->credit / KHRM;
                        }
                      }
                    }else{ //no consolidate
                        if($pre['currency']==1 && $data['currency_id']==2){
                            $coa_details[$pre->coa_id]["pre_debit"] += $pre->debit/USDTOKHR;
                            $coa_details[$pre->coa_id]["pre_credit"] += $pre->credit/USDTOKHR;
                        }elseif($pre['currency']==2 && $data['currency_id']==2){
                          $coa_details[$pre->coa_id]["pre_debit"] += $pre->debit;
                          $coa_details[$pre->coa_id]["pre_credit"] += $pre->credit;
                        }
                    }

                    $coa_details[$pre->coa_id]["parent"] = $pre->parent_id;
                    $coa_details[$pre->coa_id]["code"] = $pre->account_code;
                    $coa_details[$pre->coa_id]["initial"] = substr($pre->account_code, 0, 1);
                    $coa_details[$pre->coa_id]["branch"] = $pre->branch_code;
                    $coa_details[$pre->coa_id]["currency"] = $pre->currency;
                    $coa_details[$pre->coa_id]["nbc_code"] = $pre->nbc_code;
                    $coa_details[$pre->coa_id]["cur_debit"] = 0;
                    $coa_details[$pre->coa_id]["cur_credit"] = 0;

                    $old_coa = $pre->coa_id;
                    $cnt++;
                  }
                  foreach($coa_all as $icoa){
                    foreach($coa_details as $ipre){
                      if($icoa->id == $ipre["parent"]){
                        $coa_details[$icoa->id]["pre_debit"]  = $ipre["pre_debit"];
                        $coa_details[$icoa->id]["pre_credit"]  = $ipre["pre_credit"];
                        $coa_details[$icoa->id]["cur_debit"]  = $ipre["cur_debit"];
                        $coa_details[$icoa->id]["cur_credit"]  = $ipre["cur_credit"];
                        $coa_details[$icoa->id]["parent"]  = $icoa->parent_id;
                        $coa_details[$icoa->id]["code"] = $icoa->account_code;
                        $coa_details[$icoa->id]["nbc_code"] = $icoa->nbc_code;
                        $coa_details[$icoa->id]["initial"] = substr($icoa->account_code, 0, 1);
                        $coa_details[$icoa->id]["branch"] = $pre->branch_code;
                        $coa_details[$icoa->id]["currency"] = $pre->currency;
                        if($icoa->type == 4) $coa_details[$icoa->id]["symbol"] = $icoa->symbol;
                      }
                    }
                  }
              }
              if(count($curJournalDetail) > 0){
                $cnt = 0; $old_coa = 0; $new_coa = 0;
                  foreach($curJournalDetail as $pre){
                    $new_coa = $pre->coa_id;
                    if($cnt == 0 && $new_coa != $old_coa){
                      $coa_details[$pre->coa_id]["cur_debit"] = 0;
                      $coa_details[$pre->coa_id]["cur_credit"] = 0;
                    }
                    if($data['consolidate'] == 1){
                      if($data['exhange_rate' == 1]){//if USD change to KHR mill....
                        if($pre['currency'] == 2 ){
                          $coa_details[$pre->coa_id]["cur_debit"] += $pre->debit * $rate;
                          $coa_details[$pre->coa_id]["cur_credit"] += $pre->credit * $rate;
                        }elseif($pre->currency == 1){//if KHR change to USD
                          $coa_details[$pre->coa_id]["cur_debit"] += $pre->debit / KHRM;
                          $coa_details[$pre->coa_id]["cur_credit"] += $pre->credit / KHRM;
                        }
                      }
                    }else{ //no consolidate
                        if($pre['currency']==1 && $data['currency_id']==2){
                            $coa_details[$pre->coa_id]["cur_debit"] += $pre->debit/USDTOKHR;
                            $coa_details[$pre->coa_id]["cur_credit"] += $pre->credit/USDTOKHR;
                        }elseif($pre['currency']==2 && $data['currency_id']==2){
                          $coa_details[$pre->coa_id]["cur_debit"] += floatval($pre->debit);
                          $coa_details[$pre->coa_id]["cur_credit"] += floatval($pre->credit);
                        }
                    }

                    $coa_details[$pre->coa_id]["parent"] = $pre->parent_id;
                    $coa_details[$pre->coa_id]["code"] = $pre->account_code;
                    $coa_details[$pre->coa_id]["initial"] = substr($pre->account_code, 0, 1);
                    $coa_details[$pre->coa_id]["branch"] = $pre->branch_code;
                    $coa_details[$pre->coa_id]["currency"] = $pre->currency;
                    $coa_details[$pre->coa_id]["nbc_code"] = $pre->nbc_code;

                    $old_coa = $pre->coa_id;
                    $cnt++;
                  }
                  foreach($coa_all as $icoa){
                    foreach($coa_details as $v){
                      if($icoa->id == $v["parent"]){
                        $coa_details[$icoa->id]["cur_debit"]  = $v["cur_debit"];
                        $coa_details[$icoa->id]["cur_credit"]  = $v["cur_credit"];
                        $coa_details[$icoa->id]["parent"]  = $icoa->parent_id;
                        $coa_details[$icoa->id]["code"] = $icoa->account_code;
                        $coa_details[$icoa->id]["nbc_code"] = $icoa->nbc_code;
                        $coa_details[$icoa->id]["initial"] = substr($icoa->account_code, 0, 1);
                        $coa_details[$icoa->id]["branch"] = $v->branch_code;
                        $coa_details[$icoa->id]["currency"] = $v->currencßy;
                        if($icoa->type == 4) $coa_details[$icoa->id]["symbol"] = $icoa->symbol;
                      }
                    }
                  }
                  ksort($coa_details);
              }

              $data['coa_details'] = $coa_details;
              $data['coa_all'] = $coa_all;
              $data['coa_all_nor'] = $coa_all_nor;

              return $data;
        }
    */

    if (!function_exists('get_pass_due')) {
        function get_pass_due($l, $pass_due = null, $sel_date = null, $sch_flag = AIR_SCH_FLG)
        {
            ($sel_date == null || $sel_date == '') ? $today = date('Y-m-d') : $today = date('Y-m-d', strtotime($sel_date));
            $sum_sch_prin = $sum_sch_int = $sum_sch_fee = $sum_sch_other_fee = 0;
            $sum_paid_prin = $sum_paid_int = $sum_paid_fee = $sum_paid_other_fee = 0;

            $penalty_arr = LoanCalculate::getTotalPenalty($l, $today);
            $overdue = $overdue_[$l->id] = $penalty_arr[2];
            foreach ($l->schedule as $key => $sch) {
                if (date_dif($sch->schedule_date, $today, 1, false) >= 0) {
                    $sum_sch_prin += floatval($sch->principal);
                    $sum_sch_int += floatval($sch->interest);
                    //if($sch->no != 0) $sum_sch_fee += floatval($sch->fee);
                    $sum_sch_fee += floatval($sch->fee);
                    $sum_sch_other_fee += floatval($sch->other_fee);
                }
            }
            $num_date = date_dif($l->schedule[count($l->schedule) - 1]->schedule_date, $today, 1, false);
            if ($num_date > 0) {
                $sum_sch_int += ($l->client_loan_account->balance * $l->interest_rate * 12 / 36000) * $num_date;
            }
            /*
            foreach($l->transaction_req as $pay){
              if(date_dif($pay->trans_date, $today, 1 ,false) >= 0) {
                //if($pay->trans_type == "Loan Repayment" || $pay->trans_type == "Auto Loan Repayment" || $pay->trans_type == "Pay-Off"){
                    $sum_paid_fee += floatval($pay->fee);
                    $sum_paid_prin += floatval($pay->principal);
                    $sum_paid_int += floatval($pay->interest);
                //}
              }
            }
            */
            foreach ($l->payment as $pay) {
                if (date_dif($pay->repayment_date, $today, 1, false) >= 0) {
                    //if($pay->trans_type == "Loan Repayment" || $pay->trans_type == "Auto Loan Repayment" || $pay->trans_type == "Pay-Off"){
                    $sum_paid_fee += floatval($pay->paid_fee);
                    $sum_paid_prin += floatval($pay->paid_principal);
                    $sum_paid_int += floatval($pay->paid_interest);
                    $sum_paid_other_fee += floatval($pay->paid_other_fee);
                }
            }
            //if($l->id == 91) {var_dump($sum_sch_fee);var_dump($sum_paid_fee); dd($l->transaction_req);}
            $pass_due->overdue = $overdue;
            $pass_due->principal = $sum_sch_prin - $sum_paid_prin;
            if ($sch_flag == "1") { // follow Schedule
                $pass_due->interest = (($sum_sch_int - $sum_paid_int) < 0) ? 0 : $sum_sch_int - $sum_paid_int;
            } else { // follow AIR
                $pass_due->interest = get_journal_bal($l->client_loan_account->air_id, $today)['balance'];
            }
            $pass_due->fee = $sum_sch_fee - $sum_paid_fee;
            $pass_due->other_fee = $sum_sch_other_fee - $sum_paid_other_fee;
            $pass_due->penalty = $penalty_arr[4];
            $pass_due->total = $pass_due->principal + $pass_due->interest + $pass_due->penalty + $pass_due->fee + $pass_due->other_fee;

            return $pass_due;
        }
    }
    if (!function_exists('get_balance')) {
        function get_balance($balance, $transaction, $entry_date = null)
        {
            (is_null($entry_date) || empty($entry_date)) ? $entry_date = date('Y-m-d') : $entry_date = date('Y-m-d', strtotime($entry_date));
            foreach ($transaction as $tr) {
                if (date_dif($tr->trans_date, $entry_date, 1, false) < 0) break;
                $balance -= $tr->principal;
            }
            return $balance;
        }
    }
    if (!function_exists('get_journal_bal')) {
        function get_journal_bal($coa_id, $entry_date = null, $type = "Dr")
        {
            (is_null($entry_date) || empty($entry_date)) ? $entry_date = date('Y-m-d') : $entry_date = date('Y-m-d', strtotime($entry_date));
            //dd($entry_date);
            //$jds = JournalRequiry::with('detail')->where('entry_date', '<', date('Y-m-d', strtotime($entry_date . '+1 day')))->get();
            $jds = JournalDetail::select('journal_detail.id as jd_id', 'debit', 'credit', 'branch_code', 'coa_id',
                'journal_id', 'journal_requiry.is_audit as audit', 'entry_date',
                'journal_detail.description')
                ->join('journal_requiry', 'journal_detail.journal_id', '=', 'journal_requiry.id')
                ->where('coa_id', $coa_id)
                ->where('journal_requiry.is_audit', 1)
                ->where('entry_date', '<', date('Y-m-d', strtotime($entry_date . '+1 day')))
                ->orderBy('journal_detail.id', 'DESC')->get();
            $t_debit = 0;
            $t_credit = 0;
            $balance = 0;
            $desc = "";
            $last_jds = $jds->first();
            $last_acc_date = date('Y-m-d', strtotime($last_jds->entry_date));
            $t_debit = $jds->sum('debit');
            $t_credit = $jds->sum('credit');
            $desc = $last_jds->description;
            $balance = $t_debit - $t_credit;
            if ($type == "Cr") $balance = -1 * $balance;
            return ['balance' => Round($balance, 4), 'last_acc_date' => $last_acc_date, 't_credit' => $t_credit, 't_debit' => $t_debit, 'desc' => $desc];
        }
    }
    if (!function_exists('get_journal_bal_restructure')) {
        function get_journal_bal_restructure($client_loan_account, $entry_date = null, $type = "Dr",$loan_id = 0)
        {
            if($type == null){
                $type = "Dr";
            }

            $last_date_option = "by_payment";
            // $last_date_option = "by_jurnal";
            if($last_date_option == 'by_jurnal'){

                (is_null($entry_date) || empty($entry_date)) ? $entry_date = date('Y-m-d') : $entry_date = date('Y-m-d', strtotime($entry_date));
                //dd($entry_date);
                $coa_id = $client_loan_account->coa_id;
                $air_id = $client_loan_account->air_id;
                //$jds = JournalRequiry::with('detail')->where('entry_date', '<', date('Y-m-d', strtotime($entry_date . '+1 day')))->get();
                $jds = JournalDetail::select('journal_detail.id as jd_id',
                                            'debit',
                                            'credit',
                                            'branch_code',
                                            'coa_id',
                                            'journal_id',
                                            'journal_requiry.is_audit as audit',
                                            'entry_date',
                                            'journal_detail.description')
                    ->join('journal_requiry', 'journal_detail.journal_id', '=', 'journal_requiry.id')
                    ->where(function ($query) use ($coa_id, $air_id) {
                        $query->where('journal_detail.coa_id', $coa_id);
                        // ->orWhere('journal_detail.coa_id', $air_id);
                    })
                    ->where('journal_requiry.is_audit', 1)
                    ->where('journal_requiry.entry_date', '<', date('Y-m-d', strtotime($entry_date . '+1 day')))
                    ->orderBy('journal_requiry.entry_date', 'DESC')
                    ->orderBy('journal_detail.id', 'DESC')->get();

                $desc = "";
                $last_jds = $jds->first();
                if ($last_jds) {
                    $last_acc_date = date('Y-m-d', strtotime($last_jds->entry_date));
                } else {
                    $last_acc_date = null;
                }
            }else{
                $last_payment = LoanPayments::where('loan_id',$loan_id)
                                            ->orderBy('payment_month','DESC')
                                            ->orderBy('repayment_date','DESC')->get()->first();   
                                            
                if($last_payment){
                    $schedule = RepaymentSchedule::where('loan_id', $loan_id)
                                            ->where('no', $last_payment->payment_month)
                                            ->first();
                // Change on 12-08-2024 for funtion clear dd
                // $last_acc_date = date('Y-m-d', strtotime($schedule->schedule_date)); // before
                $check_payment = LoanPayments::select('*')
                ->where('payment_month',$last_payment->payment_month)
                ->where('loan_id',  $loan_id)
                ->get();
                $sum_paid_interest=0;
                if (!empty($check_payment) || count($check_payment)>0) { 
                    $sum_paid_interest=floatval($check_payment->sum('paid_interest')); 
                }
                
                 if(Round($sum_paid_interest, 2)>=Round($schedule->interest, 2)){
                    $last_acc_date = date('Y-m-d', strtotime($schedule->schedule_date));
                 }else{
                    $schedule = RepaymentSchedule::where('loan_id', $loan_id)
                    ->where('no', $last_payment->payment_month -1)
                    ->first();
                    if($schedule){
                        $last_acc_date = date('Y-m-d', strtotime($schedule->schedule_date));
                    }
                   
                 }
                //  End ===========================================================
                   
                }else{
                    $last_acc_date = null;
                }
            }

            return ['last_acc_date' => $last_acc_date];
        }
    }
    if (!function_exists('get_journal_bal_new')) {
        function get_journal_bal_new($coa_id, $entry_date = null, $type = "Dr")
        {
            (is_null($entry_date) || empty($entry_date)) ? $entry_date = date('Y-m-d') : $entry_date = date('Y-m-d', strtotime($entry_date));
            //dd($entry_date);
            //$jds = JournalRequiry::with('detail')->where('entry_date', '<', date('Y-m-d', strtotime($entry_date . '+1 day')))->get();
            $jds = JournalDetail::select('journal_detail.id as jd_id', 'debit', 'credit', 'branch_code', 'coa_id',
                'journal_id', 'journal_requiry.is_audit as audit', 'entry_date',
                'journal_detail.description')
                ->join('journal_requiry', 'journal_detail.journal_id', '=', 'journal_requiry.id')
                ->where('coa_id', $coa_id)
                ->where('journal_requiry.is_audit', 1)
                ->where('entry_date', '<', date('Y-m-d', strtotime($entry_date . '+1 day')))
                ->orderBy('journal_detail.id', 'DESC')->get();
            $t_debit = 0;
            $t_credit = 0;
            $balance = 0;
            $desc = "";
            $last_jds = $jds->first();
            $last_acc_date = isset($last_jds->entry_date) ? date('Y-m-d', strtotime($last_jds->entry_date)) : '-';
            $t_debit = $jds->sum('debit');
            $t_credit = $jds->sum('credit');
            $desc = $last_jds->description;
            $balance = $t_debit - $t_credit;
            if ($type == "Cr") $balance = -1 * $balance;
            return ['balance' => Round($balance, 4), 'last_acc_date' => $last_acc_date, 't_credit' => $t_credit, 't_debit' => $t_debit, 'desc' => $desc];
        }
    }
    if (!function_exists('get_journal_bal_bal')) {
        function get_journal_bal_bal($coa_id, $start_date = null, $end_date = null, $type = "Dr")
        {
            (is_null($start_date) || empty($start_date)) ? $start_date = date('Y-m-d', strtotime(MFI_START_DATE)) : $start_date = date('Y-m-d', strtotime($start_date));

            (is_null($end_date) || empty($end_date)) ? $end_date = date('Y-m-d') : $end_date = date('Y-m-d', strtotime($end_date));
            //dd($entry_date);
            //$jds = JournalRequiry::with('detail')->where('entry_date', '<', date('Y-m-d', strtotime($entry_date . '+1 day')))->get();
            $jds_start = JournalDetail::select('journal_detail.id as jd_id', 'p_debit', 'p_credit',
                'debit', 'credit', 'b_debit', 'b_credit', 'branch_code', 'coa_id',
                'journal_id', 'journal_requiry.is_audit as audit', 'entry_date', 'journal_detail.description as desc')
                ->join('journal_requiry', 'journal_detail.journal_id', '=', 'journal_requiry.id')
                ->where('coa_id', $coa_id)
                ->where('journal_requiry.is_audit', 1);
            $jds_end = clone $jds_start;


            $jds_start = $jds_start->where('entry_date', '>', date('Y-m-d', strtotime($start_date)))
                ->orderBy('journal_requiry.id', 'ASC')->orderBy('entry_date', 'ASC')
                ->first();
            $jds_end = $jds_end->where('entry_date', '<', date('Y-m-d', strtotime($end_date . '+1 day')))
                ->orderBy('journal_requiry.id', 'DESC')->orderBy('entry_date', 'DESC')
                ->orderBy('journal_detail.id', 'DESC')
                ->first();
            //             if($coa_id == 13807) dd($jds_end);
            $st_debit_bal = $jds_start->p_debit;
            $st_credit_bal = $jds_start->p_credit;
            $end_debit_bal = $jds_end->b_debit;
            $end_credit_bal = $jds_end->b_credit;
            // if last_date < start_date
            if (date_dif($jds_end->entry_date, $start_date, 1, false) > 0) {
                $st_debit_bal = $jds_end->b_debit;
                $st_credit_bal = $jds_end->b_credit;
            }
            $cur_to_debit = $end_debit_bal - $st_debit_bal;
            $cur_to_credit = $end_credit_bal - $st_credit_bal;
            $balance = $end_debit_bal - $end_credit_bal;
            if ($type == "Cr") $balance = -1 * $balance;


            return ['balance' => Round($balance, 4), 'last_acc_date' => $jds_end->entry_date, 'desc' => $jds_end->desc,
                'cur_to_debit' => $cur_to_debit, 'cur_to_credit' => $cur_to_credit,
                'st_debit_bal' => $st_debit_bal, 'st_credit_bal' => $st_credit_bal,
                'end_debit_bal' => $end_debit_bal, 'end_credit_bal' => $end_credit_bal
            ];
        }
    }

    if (!function_exists('get_balance_obj')) {
        function get_balance_obj($obj, $arg = null, $val = null)
        {
            $balance = 0;
            $obj = $obj->where($arg, $val);
            if (count($obj) == 0) {
                $balance = 0;
            } else {
                foreach ($obj as $o) {
                    $balance += $o->debit - $o->credit;
                }
            }
            return $balance;
        }
    }

    if (!function_exists('get_journal_bal_sp')) {
        function get_journal_bal_sp($arg = null, $operator = null, $val = null, $entry_date = null)
        {

            (is_null($entry_date) || empty($entry_date)) ? $entry_date = date('Y-m-d') : $entry_date = date('Y-m-d', strtotime($entry_date));
            //dd($entry_date);
            //$jds = JournalRequiry::with('detail')->where('entry_date', '<', date('Y-m-d', strtotime($entry_date . '+1 day')))->get();
            $jds = JournalDetail::select('journal_detail.id as jd_id', 'debit', 'credit', 'branch_code', 'coa_id',
                'journal_id', 'journal_requiry.is_audit as audit', 'entry_date',
                'journal_detail.description')
                ->join('journal_requiry', 'journal_detail.journal_id', '=', 'journal_requiry.id')
                //->where('coa_id', $coa_id)
                ->where($arg, $operator, $val)
                ->where('journal_requiry.is_audit', 1)
                ->where('entry_date', '<', date('Y-m-d', strtotime($entry_date . '+1 day')))
                ->orderBy('journal_detail.id', 'DESC')->get();
            $t_debit = 0;
            $t_credit = 0;
            $balance = 0;
            $desc = "";
            $last_jds = $jds->first();
            $last_acc_date = date('Y-m-d', strtotime($last_jds->entry_date));
            $t_debit = $jds->sum('debit');
            $t_credit = $jds->sum('credit');
            $desc = $last_jds->description;
            $balance = $t_debit - $t_credit;
            if ($type == "Cr") $balance = -1 * $balance;
            return ['balance' => Round($balance, 4), 'last_acc_date' => $last_acc_date, 't_credit' => $t_credit, 't_debit' => $t_debit, 'desc' => $desc];
        }
    }
    if (!function_exists('get_last_sch_int')) {
        function get_last_sch_int($loan_acc_id, $transaction, $interest_rate, $entry_date = null)
        {
            $loan_account = ClientLoanAccounts::find($loan_acc_id);
            $air_arr = get_journal_bal($loan_account->air_id, $entry_date);
            $last_bal = $transaction[count($transaction) - 1]->balance;
            $sch_interest = $air_arr["balance"] + date_dif($air_arr["last_acc_date"], $entry_date, 1, false) * $last_bal * ($interest_rate / 100) * (12 / 360);
            return Round($sch_interest, 2);
        }
    }

    if (!function_exists('get_auto_provision_old')) {
        function get_auto_provision_old($loan_acc, $loan, $i_overdue)
        {
            /*New Prakas
            Classification	Number of days past due		Allowance rate
            Standard	Zero to 30 days (short-term)	0%
                    Zero to 30 days (long-term)
            Substandard	31 days to 60 days (short-term)	10%
                    31 days to 180 days (long-term)
            Doubtful	61 days to 90 days (short-term)	30%
                    181 days to 360 days (long-term)
            Loss		More than 91 days (short-term)	100%
                    361 days or more (long-term)

            */
            $exp_status = "-";
            $act_status = "-";
            if ($loan_acc->status >= 0 && $loan_acc->status < 6) {
                //Short-term loan
                if (($loan->frequency == "M" && $loan->loan_duration <= 12) || ($loan->frequency == "Y" && $loan->loan_duration <= 1)) {
                    if ($i_overdue <= 30) $exp_status = 2; //"Standard";
                    elseif ($i_overdue <= 60) $exp_status = 3; //"Substandard";
                    elseif ($i_overdue <= 90) $exp_status = 4; //"Doubtful";
                    else $exp_status = 5; //"Loss";
                }
                //Long-term loan
                if (($loan->frequency == "M" && $loan->loan_duration > 12) || ($loan->frequency == "Y" && $loan->loan_duration > 1)) {
                    if ($i_overdue <= 30) $exp_status = 2; //"Standard";
                    elseif ($i_overdue <= 180) $exp_status = 3; //"Substandard";
                    elseif ($i_overdue <= 360) $exp_status = 4; //"Doubtful";
                    else $exp_status = 5; //"Loss";
                }
            }
            //if($sum->status >= $exp_status) continue;
            //$this::auto_exe_provision($sum, $exp_status);
            return $exp_status;
        }
    }

    if (!function_exists('get_auto_provision')) {
        function get_auto_provision($loan_acc, $loan, $i_overdue)
        {
            /*New Prakas
            Classification	Number of days past due		Allowance rate
            Standard	Zero to 14 days (short-term)	1%
                    Zero to 29 days (long-term)
            Special mention	15 days to 30 days (short-term)	3%
                    30 days to 89 days (long-term)
            Substandard	31 days to 60 days (short-term)	20%
                    90 days to 179 days (long-term)
            Doubtful	61 days to 90 days (short-term)	50%
                    180 days to 359 days (long-term)
            Loss		More than 91 days (short-term)	100%
                    360 days or more (long-term)

            */
            $exp_status = "-";
            $act_status = "-";
            if ($loan_acc->status >= 0 && $loan_acc->status <= 6) {
                //Short-term loan
                if (($loan->frequency == "M" && $loan->loan_duration <= 12) || ($loan->frequency == "Y" && $loan->loan_duration <= 1)) {
                    if ($i_overdue <= 14) $exp_status = 2; //"Standard";
                    elseif ($i_overdue <= 30) $exp_status = 3; //"Special Mention";
                    elseif ($i_overdue <= 60) $exp_status = 4; //"Substandard";
                    elseif ($i_overdue <= 90) $exp_status = 5; //"Doubtful";
                    else $exp_status = 6; //"Loss";
                }
                //Long-term loan
                if (($loan->frequency == "M" && $loan->loan_duration > 12) || ($loan->frequency == "Y" && $loan->loan_duration > 1)) {
                    if ($i_overdue <= 29) $exp_status = 2; //"Standard";
                    elseif ($i_overdue <= 89) $exp_status = 3; //"Special Mention";
                    elseif ($i_overdue <= 179) $exp_status = 4; //"Substandard";
                    elseif ($i_overdue <= 359) $exp_status = 5; //"Doubtful";
                    else $exp_status = 6; //"Loss";
                }
            }
            //if($sum->status >= $exp_status) continue;
            //$this::auto_exe_provision($sum, $exp_status);
            return $exp_status;
        }
    }
    if (!function_exists('get_journal_provision')) {
        function get_journal_provision($loan_account, $amount, $old_status, $new_status, $opt = "")
        {
            $static = config('static_data');
            /** general provision **/
            if (CLASS_NEW_PRAKAS == 0) {  // old prakas
                $lc_status = $static['client_loan_account_status'];
                $provision_rate = $static['provision_rate'];
            } else {
                $lc_status = $static['client_loan_account_status_new'];
                $provision_rate = $static['provision_rate_new'];
            }
            if ($old_status == $new_status) {
                $status = $old_status;
                $prov_rate = $provision_rate[$status];
                if ($prov_rate == 0) return [];
                if ($opt == "down") {
                    $provision_amount = $prov_rate * $amount / 100;
                    $journal_arr = [];
                    $desc_str = "provision " . $loan_account->loan_ref . "(" . $lc_status[$old_status] . " -> " . $lc_status[$next_status] . ")";
                    $desc_str .= ' - prov amount(' . $prov_rate . '% x ' . $amount . ' ) = ' . $provision_amount;
                    $pat1 = '%' . substr($loan_account->acc_key, 0, 7) . '%';
                    if (strpos($loan_account->acc_key, '<') !== false) {
                        $pat2 = '%<%';
                    } elseif (strpos($loan_account->acc_key, '>') !== false) {
                        $pat2 = '%>%';
                    }
                    $debit_acc = CoaCategory::select('*')->where('name', 'LIKE', 'Exp-Bad and Doubtful Debt%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE', $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                    $credit_acc = CoaCategory::select('*')->where('name', 'LIKE', '(Less) General Loan Loses%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE', $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                } else {
                    $provision_amount = $prov_rate * $amount / 100;
                    $journal_arr = [];
                    $desc_str = "provision back" . $loan_account->loan_ref . "(" . $lc_status[$old_status] . " -> " . $lc_status[$next_status] . ")";
                    $desc_str .= ' - prov amount(' . $prov_rate . '% x ' . $amount . ' ) = ' . $provision_amount;
                    $pat1 = '%' . substr($loan_account->acc_key, 0, 7) . '%';
                    if (strpos($loan_account->acc_key, '<') !== false) {
                        $pat2 = '%<%';
                    } elseif (strpos($loan_account->acc_key, '>') !== false) {
                        $pat2 = '%>%';
                    }
                    $credit_acc = CoaCategory::select('*')->where('name', 'LIKE', 'Exp-Bad and Doubtful Debt%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE', $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                    $debit_acc = CoaCategory::select('*')->where('name', 'LIKE', '(Less) General Loan Loses%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE', $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                }
            } else {
                /** Specific Provision **/
                if ($opt == "down") { // downgrad
                    $prov_rate = 0;
                    for ($status = $old_status; $status < $new_status; $status++) {
                        $prov_rate += $provision_rate[$status + 1];
                    }
                    $provision_amount = $prov_rate * $amount / 100;
                    $journal_arr = [];
                    $desc_str = "provision " . $loan_account->loan_ref . "(" . $lc_status[$old_status] . " -> " . $lc_status[$new_status] . ")";
                    $desc_str .= ' - prov amount(' . $prov_rate . '% x ' . $amount . ' ) = ' . $provision_amount;
                    $pat1 = '%' . substr($loan_account->acc_key, 0, 7) . '%';
                    if (strpos($loan_account->acc_key, '<') !== false) {
                        $pat2 = '%<%';
                    } elseif (strpos($loan_account->acc_key, '>') !== false) {
                        $pat2 = '%>%';
                    }
                    $debit_acc = CoaCategory::select('*')->where('name', 'LIKE', 'Exp-Bad and Doubtful Debt%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE', $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                    $credit_acc = CoaCategory::select('*')->where('name', 'LIKE', '(Less) Specific Loan Loses%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE', $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                } else { //upgrade
                    $prov_rate = 0;
                    for ($status = $old_status; $status > $new_status; $status--) {
                        $prov_rate += $provision_rate[$status];
                    }
                    $provision_amount = $prov_rate * $amount / 100;
                    $journal_arr = [];
                    $desc_str = "provision back" . $loan_account->loan_ref . "(" . $lc_status[$old_status] . " -> " . $lc_status[$new_status] . ")";
                    $desc_str .= ' - prov amount(' . $prov_rate . '% x ' . $amount . ' ) = ' . $provision_amount;
                    $pat1 = '%' . substr($loan_account->acc_key, 0, 7) . '%';
                    if (strpos($loan_account->acc_key, '<') !== false) {
                        $pat2 = '%<%';
                    } elseif (strpos($loan_account->acc_key, '>') !== false) {
                        $pat2 = '%>%';
                    }
                    $credit_acc = CoaCategory::select('*')->where('name', 'LIKE', 'Exp-Bad and Doubtful Debt%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE', $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                    $debit_acc = CoaCategory::select('*')->where('name', 'LIKE', '(Less) Specific Loan Loses%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE', $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                }
            }
            array_push($journal_arr, [$debit_acc->id, $provision_amount, $desc_str, $credit_acc->id, $provision_amount, $desc_str, $desc_str]);
            return $journal_arr;
        }
    }
    if (!function_exists('get_auto_payment_actual')) {
        function get_auto_payment_actual($l, $verify_date, $amount = "")
        {
            // ORO loan
            $ORO_flg = (strpos($l->contract_id, 'LC') !== FALSE) ? 1 : 0;
            $loan_status = intval($l->client_loan_account->status);
            $repay_arr = [];
            if ($l->status != 3 && $l->status != 8) continue;
            //if($l->id == 162) dd($l);
            $penalty_arr = LoanCalculate::getTotalPenalty($l, $verify_date);
            $overdue = $penalty_arr[2];
            if (count($penalty_arr[0]) == 0) continue;
            $sch_repay_arr = get_auto_repay_array($l, $verify_date);
            $act_interest = $act_principal = $act_penalty = $act_fee = 0;
            //$new_balance = $d->balance;
            // $dd_bal_arr[$d->id] = -get_journal_bal($d->coa_id, $verify_date)["balance"];
            // $new_balance = $dd_bal_arr[$d->id];
            if ($amount == "") {
                $new_balance = -get_journal_bal($d->coa_id, $verify_date)["balance"];
            } else {
                $new_balance = $amount;
            }
//dd($sch_repay_arr);
            if (round($new_balance, 2) == 0 || empty($sch_repay_arr) || is_null($sch_repay_arr) || count($sch_repay_arr) == 0) continue;
            foreach ($sch_repay_arr as $sch) {
                $balance = $new_balance;
                $act_interest = $act_fee = $act_penalty = $act_principal = 0;
                // interest
                $act_interest = ($balance > round($sch['interest'], 2)) ? round($sch['interest'], 2) : $balance;
                $balance -= $act_interest;
                // fee
                if ($balance > 0) {
                    $act_fee = ($balance > round($sch['fee'], 2)) ? round($sch['fee'], 2) : $balance;
                    $balance -= $act_fee;
                }
                // balloon or not
                $balloon_flag = 0;
                if ($l->frequency == "M") {
                    $bal_limit = $l->loan_amount / ($l->loan_duration * 3 / 12);
                    if ($sch["principal"] >= $bal_limit) $balloon_flag = 1;
                }

                // NPL or not
                $NPL_flg = ($loan_status > GENERAL_LC_STATUS) ? 1 : 0;
                if ($NPL_flg == 0) {  // PL customer
                    // penalty
                    if ($balance > 0) {
                        $act_penalty = ($balance > round($sch['penalty'], 2)) ? round($sch['penalty'], 2) : $balance;
                        $balance -= $act_penalty;
                    }
                    // principal
                    if ($balance > 0) {
                        $act_principal = ($balance > round($sch['principal'], 2)) ? round($sch['principal'], 2) : $balance;
                        $balance -= $act_principal;
                    }
                } else {              // NPL customer
                    // principal
                    if ($balance > 0) {
                        $act_principal = ($balance > round($sch['principal'], 2)) ? round($sch['principal'], 2) : $balance;
                        $balance -= $act_principal;
                    }
                    // penalty
                    // if($balance > 0){
                    //     $act_penalty = ($balance > round($sch['penalty'],2))? round($sch['penalty'],2) : $balance;
                    //     $balance -= $act_penalty;
                    // }
                }
                $sch['act_interest'] = $act_interest;
                $sch['act_fee'] = $act_fee;
                $sch['act_penalty'] = $act_penalty;
                $sch['act_principal'] = $act_principal;
                $sch['act_total'] = $act_interest + $act_fee + $act_penalty + $act_principal;
                if (round($sch['act_total'], 2) == 0) continue;
                $sch['balance'] = $balance;
                // repyament owed
                $sch['repayment_owed'] = $sch['principal'] + $sch['interest'] + $sch['fee'] + $sch['penalty'] - $sch['act_total'];
                // status
                if (round($sch['repayment_owed'], 2) > 0) {
                    $sch['status'] = 0;
                    $sch['condition'] = 1;
                } else {
                    $sch['status'] = 1;
                    $sch['condition'] = 0;
                }
                $sch['overdue'] = date_dif($sch['schedule_date'], $verify_date, 1, false);
                array_push($repay_arr, $sch);
                $new_balance = $balance;
                if (round($balance, 2) <= 0) break;
            }
            return $repay_arr;
        }
    }
    if (!function_exists('get_manual_payment_actual')) {
        function get_manual_payment_actual($l, $verify_date, $int_bal = 0, $fee_bal = 0, $prin_bal = 0, $penal_bal = 0, $pen_waive_flg = 0, $other_fee_bal)
        {
            // ORO loan
            $ORO_flg = (strpos($l->contract_id, 'LC') !== FALSE) ? 1 : 0;
            $loan_status = intval($l->client_loan_account->status);
            $repay_arr = [];
            if ($l->status != 3 && $l->status != 8) return $repay_arr;
            //if($l->id == 162) dd($l);
            $penalty_arr = LoanCalculate::getTotalPenalty($l, $verify_date);
            $overdue = $penalty_arr[2];
            if (count($penalty_arr[0]) == 0) return $repay_arr;
            $sch_repay_dow_arr = get_auto_repay_array_downpayment($l, $verify_date);
            // $sch_repay_arr = get_auto_repay_array($l, $verify_date);
            $act_interest = $act_principal = $act_penalty = $act_fee = $act_waive_penalty = 0;


            //$new_balance = $d->balance;
            // $dd_bal_arr[$d->id] = -get_journal_bal($d->coa_id, $verify_date)["balance"];
            // $new_balance = $dd_bal_arr[$d->id];
            // if($amount == ""){
            //     $new_balance = -get_journal_bal($d->coa_id, $verify_date)["balance"];
            // }else{
            //     $new_balance = $amount;
            // }
            //dd($sch_repay_arr);
            /* Penalty keyed against a loan with nothing due. get_auto_repay_array_downpayment only
               returns instalments that still owe something, so a customer who is completely up to
               date produces an empty array and the return below threw the teller's entry away: the
               cash reached the till and the journal, the drawdown was credited and then debited
               again by the caller, and tb_loan_payments and tb_transactions_requiry got nothing --
               the penalty was missing from the loan's own Transaction tab and from its ledger.
               LD-R104 took 2,000.00 that way on 17-Sep-2026 with instalment 9 not due until the
               25th, LD-H17 150.00 on 10-Sep-2026 the day its instalment was settled.
               Attach it to the newest instalment dated on or before the posting. That row is
               necessarily settled -- an unsettled one would have come back in the array above --
               so its schedule status is already 1 and the caller's update of it is a no-op; no
               future instalment is marked paid. Penalty-only postings, which is what
               postBCashLoanPenaltyFee sends: anything carrying principal, interest, fee or other
               fee still returns as before. */
            if ((empty($sch_repay_dow_arr) || count($sch_repay_dow_arr) == 0)
                && round($penal_bal, 2) > 0
                && round($int_bal, 2) == 0 && round($fee_bal, 2) == 0
                && round($prin_bal, 2) == 0 && round($other_fee_bal, 2) == 0) {
                $settled_sch = RepaymentSchedule::where('loan_id', $l->id)
                    ->where('schedule_date', '<=', $verify_date)
                    ->orderBy('no', 'desc')->first();
                if (!empty($settled_sch)) {
                    $sch_repay_dow_arr[$settled_sch->type][] = [
                        'loan_id'       => $l->id,
                        'month_idx'     => $settled_sch->no,
                        'schedule_date' => $settled_sch->schedule_date,
                        'interest'      => 0,
                        'fee'           => 0,
                        'other_fee'     => 0,
                        'principal'     => 0,
                        'penalty'       => round($penal_bal, 2),
                        'total'         => round($penal_bal, 2),
                        'type'          => $settled_sch->type
                    ];
                }
            }
            $balance = $int_bal + $fee_bal + $prin_bal + $penal_bal + $other_fee_bal;
            if (round($balance, 2) == 0 || empty($sch_repay_dow_arr) || is_null($sch_repay_dow_arr) || count($sch_repay_dow_arr) == 0) {
                if ($pen_waive_flg == 0) return $repay_arr;
            }
            
            /* Manual penalty inside the down-payment period. Down-payment instalments never
               accrue penalty, so a loan still paying its down payment reports 0 owed and the
               penalty cap below would throw away an amount the teller was told to collect.
               Detect that case here, before allocating: only when NOTHING has accrued anywhere
               on this loan does the down-payment row accept the keyed amount. A loan carrying
               real accrued penalty is allocated exactly as before. */
            $accrued_penalty_total = 0;
            foreach ($sch_repay_dow_arr as $t_arr) {
                foreach ($t_arr as $t_sch) {
                    $accrued_penalty_total += round($t_sch['penalty'], 2);
                }
            }
            $manual_dp_penalty = ($accrued_penalty_total <= 0);

            foreach ($sch_repay_dow_arr as $key => $sch_repay_arr) {
                foreach ($sch_repay_arr as $sch) {
                    $act_interest = $act_fee = $act_penalty = $act_principal = $act_other_fee = 0;
                    // interest
                    if ($int_bal > 0) {
                        $act_interest = ($int_bal > round($sch['interest'], 2)) ? round($sch['interest'], 2) : $int_bal;
                        $int_bal -= $act_interest;
                    }
                    // fee
                    if ($fee_bal > 0) {
                        $act_fee = ($fee_bal > round($sch['fee'], 2)) ? round($sch['fee'], 2) : $fee_bal;
                        $fee_bal -= $act_fee;
                    }
                    // other fee
                    if ($other_fee_bal > 0) {
                        $other_fee_bal = ($other_fee_bal > round($sch['other_fee'], 2)) ? round($sch['other_fee'], 2) : $other_fee_bal;
                        $other_fee_bal -= $other_fee_bal;
                    }
                    // NPL or not
                    // $NPL_flg = ($overdue > 30 || $loan_status > GENERAL_LC_STATUS)? 1 : 0;
                    // if($NPL_flg == 0){  // PL customer
                    //     // penalty
                    if ($pen_waive_flg == 0) {
                        if ($penal_bal > 0) {
                            $row_penalty = round($sch['penalty'], 2);
                            /* A down-payment instalment never accrues penalty (the
                               $sch->type == 'downpayment' branches of get_auto_repay_array_downpayment
                               all do $sch_penalty += 0), so $row_penalty is 0 during the down-payment
                               period and the cap below discarded whatever the teller keyed in: the cash
                               reached the till as a 'Penalty Fee' deposit and appeared on R11, while the
                               loan ledger recorded 0.00 and the two could never reconcile. When nothing
                               has accrued anywhere on this loan, record the keyed amount against the
                               down-payment row instead. Accrual is unchanged -- nothing is charged unless
                               a teller enters it, and any loan that has accrued penalty keeps the old cap. */
                            if ($manual_dp_penalty && $row_penalty <= 0 && isset($sch['type']) && $sch['type'] == 'downpayment') {
                                $act_penalty = $penal_bal;
                            } else {
                                $act_penalty = ($penal_bal > $row_penalty) ? $row_penalty : $penal_bal;
                            }
                            $penal_bal -= $act_penalty;
                        }
                    } else { // penalty waive
                        if ($penal_bal <= 0) { // waive all
                            $act_penalty = 0;
                            $act_waive_penalty = round($sch['penalty'], 2);
                        } else {  // waive partially
                            $act_penalty = ($penal_bal > round($sch['penalty'], 2)) ? round($sch['penalty'], 2) : $penal_bal;
                            $penal_bal -= $act_penalty;
                            if ($penal_bal <= 0) $act_waive_penalty = round($sch['penalty'], 2) - $act_penalty;
                        }
                    }
                    // principal
                    if ($prin_bal > 0) {
                        $act_principal = ($prin_bal > round($sch['principal'], 2)) ? round($sch['principal'], 2) : $prin_bal;
                        $prin_bal -= $act_principal;
                    }
                    // }else{              // NPL customer
                    //     // principal
                    //     if($prin_bal > 0){
                    //         $act_principal = ($prin_bal > round($sch['principal'],2))? round($sch['principal'],2) : $prin_bal;
                    //         $prin_bal -= $act_principal;
                    //     }
                    // penalty
                    // if($balance > 0){
                    //     $act_penalty = ($balance > round($sch['penalty'],2))? round($sch['penalty'],2) : $balance;
                    //     $balance -= $act_penalty;
                    // }
                    // }
                    $sch['act_interest']      = $act_interest;
                    $sch['act_fee']           = $act_fee;
                    $sch['act_other_fee']     = $act_other_fee;
                    $sch['act_penalty']       = $act_penalty;
                    $sch['act_principal']     = $act_principal;
                    $sch['act_total']         = $act_interest + $act_fee + $act_other_fee + $act_penalty + $act_principal;
                    $sch['act_waive_penalty'] = $act_waive_penalty;
                    //if(round($sch['act_total'], 2) == 0 && $pen_waive_flg == 0 && round($sch['total'],2) == 0) continue;
                    if (round($sch['act_total'], 2) == 0 && $pen_waive_flg == 0) continue;
                    $balance = $int_bal + $fee_bal + $prin_bal + $penal_bal;
                    $sch['balance'] = round($balance, 2);
                    // repyament owed
                    $sch['repayment_owed'] = $sch['principal'] + $sch['interest'] + $sch['fee'] + $sch['penalty'] - ($sch['act_total'] - $sch['act_other_fee']) - $sch['act_waive_penalty'];

                    // status
                    if (round($sch['repayment_owed'], 2) > 0) {
                        $sch['status'] = 0;
                        $sch['condition'] = 1;
                    } else {
                        $sch['status'] = 1;
                        $sch['condition'] = 0;
                    }
                    $sch['overdue'] = date_dif($sch['schedule_date'], $verify_date, 1, false);

                    // array_push($repay_arr, $sch);

                    $repay_arr[$sch['type']][] = $sch;

                    if (round($balance, 2) <= 0 && $pen_waive_flg == 0) break;

                }
            }
            return $repay_arr;
        }

        if (!function_exists('get_total_int_till_today')) {
            function get_total_int_till_today($loan, $client_loan_account, $entry_date)
            {
                (is_null($entry_date) || empty($entry_date)) ? $entry_date = date('Y-m-d') : $entry_date = date('Y-m-d', strtotime($entry_date));
                $ex_air_arr = get_journal_bal($client_loan_account->air_id, $entry_date);
                $ex_air_amount = $ex_air_arr["balance"];
                $days = date_dif($ex_air_arr["last_acc_date"], $entry_date, 1, false);
                $new_air_amount = $days * $client_loan_account->balance * $loan->interest_rate * 12 / 36000;

                return ['ex_air_amount' => Round($ex_air_amount, 4), 'new_air_amount' => Round($new_air_amount, 4)];
            }
        }
        if (!function_exists('get_total_int_till_today_coa')) {
            function get_total_int_till_today_coa($loan, $client_loan_account, $entry_date)
            {
                (is_null($entry_date) || empty($entry_date)) ? $entry_date = date('Y-m-d') : $entry_date = date('Y-m-d', strtotime($entry_date));
                // $ex_air_arr = get_journal_bal($client_loan_account->air_id, $entry_date); Change Date 
                $ex_air_arr = get_journal_bal($client_loan_account->coa_id, $entry_date);
                $ex_air_amount = $ex_air_arr["balance"];
                $days = date_dif($ex_air_arr["last_acc_date"], $entry_date, 1, false);
                $new_air_amount = $days * $client_loan_account->balance * $loan->interest_rate * 12 / 36000;

                return ['ex_air_amount' => Round($ex_air_amount, 4), 'new_air_amount' => Round($new_air_amount, 4)];
            }
        }

        if (!function_exists('get_total_int_till_today_new')) {
            function get_total_int_till_today_new($loan, $client_loan_account, $entry_date)
            {
                (is_null($entry_date) || empty($entry_date)) ? $entry_date = date('Y-m-d') : $entry_date = date('Y-m-d', strtotime($entry_date));
                $ex_air_arr = get_journal_bal($client_loan_account->air_id, $entry_date);
                $ex_air_amount = $ex_air_arr["balance"];
                $days = date_dif($ex_air_arr["last_acc_date"], $entry_date, 1, false);
                $new_air_amount = $days * $client_loan_account->balance * $loan->interest_rate * 12 / 36000;

                return ['ex_air_amount' => Round($ex_air_amount, 4), 'new_air_amount' => Round($new_air_amount, 4)];
            }
        }

        if (!function_exists('get_total_int_till_today_restructure')) {
            function get_total_int_till_today_restructure($loan, $client_loan_account = 0, $entry_date, $loan_amount = 0)
            {
                (is_null($entry_date) || empty($entry_date)) ? $entry_date = date('Y-m-d') : $entry_date = date('Y-m-d', strtotime($entry_date));
                $ex_air_arr = get_journal_bal_restructure($client_loan_account, $entry_date,null,$loan->id);
                if (isset($ex_air_arr['last_acc_date'])) {
                    $last_entry_date = $ex_air_arr['last_acc_date'];
                } else {
                    $last_entry_date = $loan->disburse_date;
                }
                $ex_air_amount = $ex_air_arr["balance"];
                $days = date_dif($last_entry_date, $entry_date, 1, false);
                $interest_amount = $loan_amount * ($days * (($loan->interest_rate * 12) / 100)) / 360;
                // $interest_amount = $days * $loan_amount * $loan->interest_rate * 12/36000;
                $data['days'] = $days;
                $data['interest'] = Round($interest_amount, 4);
                return $data;
            }
        }
        //Update Balance in JD
        if (!function_exists('exe_auto_add_jd_bal')) {
            function exe_auto_add_jd_bal($coa_id = 0)
            {

                $jds = JournalDetail::select('journal_detail.id as jd_id', 'p_debit', 'p_credit', 'debit', 'credit',
                    'b_credit', 'b_debit', 'branch_code', 'coa_id',
                    'journal_id', 'journal_requiry.is_audit as audit', 'entry_date', 'journal_detail.description')
                    ->join('journal_requiry', 'journal_detail.journal_id', '=', 'journal_requiry.id')
                    ->where('journal_requiry.is_audit', 1)
                    ->orderBy('journal_requiry.id', 'ASC')
                    ->orderBy('journal_requiry.entry_date', 'ASC');
                if ($coa_id != 0) {
                    $jds = $jds->where('coa_id', $coa_id);
                }
                $jds = $jds->get()->groupBy('coa_id');
                $p_debit = $p_credit = 0;
                $old_coa_id = 0;
                //dd($jds);
                foreach ($jds as $coa_id => $jdr) {
                    //dd($jdr);
                    $p_debit = $p_credit = 0;
                    foreach ($jdr as $jd) {
                        $jd->b_debit = $p_debit + $jd->debit;
                        $jd->b_credit = $p_credit + $jd->credit;
                        JournalDetail::where('id', $jd->jd_id)
                            ->update(['p_debit' => $p_debit, 'p_credit' => $p_credit,
                                'b_debit' => $jd->b_debit, 'b_credit' => $jd->b_credit]);
                        $p_debit = $jd->b_debit;
                        $p_credit = $jd->b_credit;
                    }
                }
                return true;
            }
        }

        //Update Balance in JD
        if (!function_exists('get_wo_paid')) {
            function get_wo_paid($loan_ref, $till_date = null)
            {
                $coa_charged_off = CoaCategory::select('id', 'name', 'type')->where('name', '=', WO_CREDIT_COA_NAME)->where('type', 6)->get();
                $coa_wo_arr = [];
                foreach ($coa_charged_off as $coa) {
                    $coa_wo_arr[] = $coa->id;
                }
                // $jd_wo_all = JournalDetail::whereIn('coa_id', $coa_wo_arr)->where('reference', $loan_ref)->get();
                $jd_wo_all = JournalDetail::select('journal_detail.*', 'journal_requiry.entry_date')
                    ->join('journal_requiry', 'journal_detail.journal_id', '=', 'journal_requiry.id')
                    ->whereIn('coa_id', $coa_wo_arr)->where('reference', $loan_ref);
                if (!is_null($till_date)) {
                    $jd_wo_all = $jd_wo_all->where('journal_requiry.entry_date', '<', date('Y-m-d', strtotime($till_date . ' +1 day')));
                }
                $jd_wo_all = $jd_wo_all->get();
                return -get_balance_obj($jd_wo_all, 'reference', $loan_ref);
            }
        }

        if (!function_exists('balance_drawdown_acc')) {
            function balance_drawdown_acc($coa_id = '')
            {
                $data = JournalDetail::selectRaw('SUM(credit - debit) AS balance')->where('coa_id', $coa_id)->where('is_audit', 1)->first()->balance;
                return $data;
            }

        }
        // if(! function_exists('get_total_int_till_today_by_sch')){
        //     function get_total_int_till_today_by_sch($loan,$client_loan_account, $entry_date){
        //         (is_null($entry_date) || empty($entry_date))? $entry_date = date('Y-m-d') : $entry_date = date('Y-m-d', strtotime($entry_date));


        //         $ex_air_arr = get_journal_bal($client_loan_account->air_id, $entry_date);
        //         $ex_air_amount = $ex_air_arr["balance"];
        //         $days = date_dif($ex_air_arr["last_acc_date"], $entry_date, 1, false);
        //         $new_air_amount = $days * $client_loan_account->balance * $loan->interest_rate * 12/36000;

        //         return ['ex_air_amount'=>Round($ex_air_amount,4),'new_air_amount'=>Round($new_air_amount,4)];
        //     }
        // }
    }

        if (!function_exists('get_manual_payment_actual_migrate')) {
        function get_manual_payment_actual_migrate($l, $verify_date, $int_bal = 0, $fee_bal = 0, $prin_bal = 0, $penal_bal = 0, $pen_waive_flg = 0, $other_fee_bal,$pmt_no = '')
        {
            // ORO loan
            $ORO_flg = (strpos($l->contract_id, 'LC') !== FALSE) ? 1 : 0;
            $loan_status = intval($l->client_loan_account->status);
            $repay_arr = [];
            if ($l->status != 3 && $l->status != 8) return $repay_arr;
            //if($l->id == 162) dd($l);
            $penalty_arr = LoanCalculate::getTotalPenalty($l, $verify_date);
            $overdue = $penalty_arr[2];
            // if (count($penalty_arr[0]) == 0) return $repay_arr;
            $sch_repay_dow_arr = get_auto_repay_array_migrate($l, $verify_date,$pmt_no);
            // $sch_repay_arr = get_auto_repay_array($l, $verify_date);
            $act_interest = $act_principal = $act_penalty = $act_fee = $act_waive_penalty = 0;


            //$new_balance = $d->balance;
            // $dd_bal_arr[$d->id] = -get_journal_bal($d->coa_id, $verify_date)["balance"];
            // $new_balance = $dd_bal_arr[$d->id];
            // if($amount == ""){
            //     $new_balance = -get_journal_bal($d->coa_id, $verify_date)["balance"];
            // }else{
            //     $new_balance = $amount;
            // }
            //dd($sch_repay_arr);
            $balance = $int_bal + $fee_bal + $prin_bal + $penal_bal + $other_fee_bal;
            if (round($balance, 2) == 0 || empty($sch_repay_dow_arr) || is_null($sch_repay_dow_arr) || count($sch_repay_dow_arr) == 0) {
                if ($pen_waive_flg == 0) return $repay_arr;
            }
            
            foreach ($sch_repay_dow_arr as $key => $sch_repay_arr) {
                foreach ($sch_repay_arr as $sch) {
                    $act_interest = $act_fee = $act_penalty = $act_principal = $act_other_fee = 0;
                    // interest
                    if ($int_bal > 0) {
                        $act_interest = ($int_bal > round($sch['interest'], 2)) ? round($sch['interest'], 2) : $int_bal;
                        $int_bal -= $act_interest;
                    }
                    // fee
                    if ($fee_bal > 0) {
                        $act_fee = ($fee_bal > round($sch['fee'], 2)) ? round($sch['fee'], 2) : $fee_bal;
                        $fee_bal -= $act_fee;
                    }
                    // other fee
                    if ($other_fee_bal > 0) {
                        $other_fee_bal = ($other_fee_bal > round($sch['other_fee'], 2)) ? round($sch['other_fee'], 2) : $other_fee_bal;
                        $other_fee_bal -= $other_fee_bal;
                    }
                    // NPL or not
                    // $NPL_flg = ($overdue > 30 || $loan_status > GENERAL_LC_STATUS)? 1 : 0;
                    // if($NPL_flg == 0){  // PL customer
                    //     // penalty
                    if ($pen_waive_flg == 0) {
                        if ($penal_bal > 0) {
                            $act_penalty = ($penal_bal > round($sch['penalty'], 2)) ? round($sch['penalty'], 2) : $penal_bal;
                            $penal_bal -= $act_penalty;
                        }
                    } else { // penalty waive
                        if ($penal_bal <= 0) { // waive all
                            $act_penalty = 0;
                            $act_waive_penalty = round($sch['penalty'], 2);
                        } else {  // waive partially
                            $act_penalty = ($penal_bal > round($sch['penalty'], 2)) ? round($sch['penalty'], 2) : $penal_bal;
                            $penal_bal -= $act_penalty;
                            if ($penal_bal <= 0) $act_waive_penalty = round($sch['penalty'], 2) - $act_penalty;
                        }
                    }
                    // principal
                    if ($prin_bal > 0) {
                        $act_principal = ($prin_bal > round($sch['principal'], 2)) ? round($sch['principal'], 2) : $prin_bal;
                        $prin_bal -= $act_principal;
                    }
                    // }else{              // NPL customer
                    //     // principal
                    //     if($prin_bal > 0){
                    //         $act_principal = ($prin_bal > round($sch['principal'],2))? round($sch['principal'],2) : $prin_bal;
                    //         $prin_bal -= $act_principal;
                    //     }
                    // penalty
                    // if($balance > 0){
                    //     $act_penalty = ($balance > round($sch['penalty'],2))? round($sch['penalty'],2) : $balance;
                    //     $balance -= $act_penalty;
                    // }
                    // }
                    $sch['act_interest'] = $act_interest;
                    $sch['act_fee'] = $act_fee;
                    $sch['act_other_fee'] = $act_other_fee;
                    $sch['act_penalty'] = $act_penalty;
                    $sch['act_principal'] = $act_principal;
                    $sch['act_total'] = $act_interest + $act_fee + $act_other_fee + $act_penalty + $act_principal;
                    $sch['act_waive_penalty'] = $act_waive_penalty;
                    //if(round($sch['act_total'], 2) == 0 && $pen_waive_flg == 0 && round($sch['total'],2) == 0) continue;
                    if (round($sch['act_total'], 2) == 0 && $pen_waive_flg == 0) continue;
                    $balance = $int_bal + $fee_bal + $prin_bal + $penal_bal;
                    $sch['balance'] = round($balance, 2);
                    // repyament owed
                    $sch['repayment_owed'] = $sch['principal'] + $sch['interest'] + $sch['fee'] + $sch['penalty'] - ($sch['act_total'] - $sch['act_other_fee']) - $sch['act_waive_penalty'];

                    // status
                    if (round($sch['repayment_owed'], 2) > 0) {
                        $sch['status'] = 0;
                        $sch['condition'] = 1;
                    } else {
                        $sch['status'] = 1;
                        $sch['condition'] = 0;
                    }
                    $sch['overdue'] = date_dif($sch['schedule_date'], $verify_date, 1, false);

                    // array_push($repay_arr, $sch);

                    $repay_arr[$sch['type']][] = $sch;

                    if (round($balance, 2) <= 0 && $pen_waive_flg == 0) break;

                }
            }
            return $repay_arr;
        }
    }
    /*
    if(!function_exists('set_provision')){
        function set_provision($loan_account, $exp_status = null){
            $static = config('static_data');
            $key_pre = $static['client_loan_account_prefix'];
            switch ($loan_account->status) {
                case 2 : // Standard -> Sub-Standard
                {
                    $sub_loan_account = $loan_account;
                    $loan_account->prefix = $key_pre[$loan_account->status]; //"Sub-Std-"
                    // Create new COA for Sub-Standard
                    // COA
                    $pre_prefix = $static['client_loan_account_pre_prefix']['coa']; //"";
                    $new_coa = createLoanAccountCoa($loan_account, $pre_prefix)['coa'];
                    //if($new_coa->new_flg == '0')
                    // AIR
                    $pre_prefix = $static['client_loan_account_pre_prefix']['air']; //"Air-";
                    $new_air = createLoanAccountCoa($loan_account, $pre_prefix)['coa'];
                    // Interest Income
                    $pre_prefix = $static['client_loan_account_pre_prefix']['interest']; //"Int-Inc-";
                    $new_int = createLoanAccountCoa($loan_account, $pre_prefix)['coa'];
                    // Income in suspense
                    $pre_prefix = $static['client_loan_account_pre_prefix']['suspense']; //'Int-In Sus-'
                    $new_int_sus = createLoanAccountCoa($sub_loan_account, $pre_prefix)['coa'];
                    // Add journal ( debit: sub_coa_id, credit: coa_id )
                    $desc_str = "provision " . $loan_account->loan_ref . "(" . $static['client_loan_account_status'][$loan_account->status] . " -> " . $static['client_loan_account_status'][$loan_account->status + 1] . ")";
                    $journal_arr = [];
                    $new_coa_id = $new_coa->id;
                    $old_coa_id = $loan_account->coa_id;
                    $old_coa_amount = JournalDetail::selectRaw('sum(debit) - sum(credit) as bal')->where('coa_id',$old_coa_id)->first()->bal;
                    if(is_null($old_coa_amount)) $old_coa_amount = 0;
                    $new_coa_amount = $old_coa_amount;
                    array_push($journal_arr, [$new_coa_id, $old_coa_amount, $desc_str, $old_coa_id, $old_coa_amount, $desc_str, $desc_str]);
                    record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);
                    // Add journal ( debit: sub_air_id, credit: air_id )
                    $journal_arr = [];
                    $new_air_id = $new_air->id;
                    $old_air_id = $loan_account->air_id;
                    $old_air_amount = JournalDetail::selectRaw('sum(debit) - sum(credit) as bal')->where('coa_id',$old_air_id)->first()->bal;
                    if(is_null($old_air_amount)) $old_air_amount = 0;
                    $new_air_amount = $old_air_amount;
                    array_push($journal_arr, [$new_air_id, $old_air_amount, $desc_str, $old_air_id, $old_air_amount, $desc_str, $desc_str]);
                    record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);
                    // Add journal ( debit: int_inc_id, credit: int_sus_id )
                    $journal_arr = [];
                    $new_int_sus_id = $new_int_sus->id;
                    $old_int_id = $loan_account->int_inc_id;
                    $old_int_amount = JournalDetail::selectRaw('sum(credit) - sum(debit) as bal')->where('coa_id',$old_int_id)->first()->bal;
                    if(is_null($old_int_amount)) $old_int_amount = 0;
                    $new_int_amount = $old_int_amount;
                    array_push($journal_arr, [$old_int_id, $old_int_amount, $desc_str, $new_int_sus_id, $old_int_amount, $desc_str, $desc_str]);
                    record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);

                    // Provision
                    $provision_amount = PROV_RATE_TO_SUB_STD * $loan_account->balance;
                    $journal_arr = [];
                    $desc_str .= ' - prov amount = '.$provision_amount;
                    $pat1 = '%'.substr($loan_account->acc_key,0,7).'%';
                    if(strpos($loan_account->acc_key,'<') !== false){
                        $pat2 = '%<%';
                    }elseif(strpos($loan_account->acc_key,'>') !== false){
                        $pat2 = '%>%';
                    }
                    $debit_acc = CoaCategory::select('*')->where('name', 'LIKE', 'Exp-Bad and Doubtful Debt%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE',  $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                    $credit_acc = CoaCategory::select('*')->where('name', 'LIKE', '(Less) Specific Loan Loses%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE',  $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                    array_push($journal_arr, [$debit_acc->id, $provision_amount, $desc_str, $credit_acc->id, $provision_amount, $desc_str, $desc_str]);
                    record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);

                    // Update loan_account
                    $loan_account->coa_id = $new_coa->id;
                    $loan_account->air_id = $new_air->id;
                    $loan_account->int_inc_id = $new_int->id;
                    $loan_account->sus_id = $new_int_sus->id;
                    $loan_account->status = $loan_account->status + 1;
                    $loan_account->save();
                    break;
                }
                case 3 : // Sub-Standard -> Doubtful
                {
                    $sub_loan_account = $loan_account;
                    $loan_account->prefix = $key_pre[$loan_account->status]; //"Doubtful "
                    // Create new COA for Doubtful
                    // COA
                    $pre_prefix = $static['client_loan_account_pre_prefix']['coa']; //"";
                    $new_coa = createLoanAccountCoa($loan_account, $pre_prefix)['coa'];
                    // AIR
                    $pre_prefix = $static['client_loan_account_pre_prefix']['air']; //"Air-";
                    $new_air = createLoanAccountCoa($loan_account, $pre_prefix)['coa'];
                    // Interest Income
                    $pre_prefix = $static['client_loan_account_pre_prefix']['interest']; //"Int-Inc-";
                    $new_int = createLoanAccountCoa($loan_account, $pre_prefix)['coa'];
                    // Income in suspense
                    $pre_prefix = $static['client_loan_account_pre_prefix']['suspense']; //'Int-In Sus-'
                    $new_int_sus = createLoanAccountCoa($sub_loan_account, $pre_prefix)['coa'];
                    // Add journal ( debit: sub_coa_id, credit: coa_id )
                    $desc_str = "provision " . $loan_account->loan_ref . "(" . $static['client_loan_account_status'][$loan_account->status] . " -> " . $static['client_loan_account_status'][$loan_account->status + 1] . ")";
                    $journal_arr = [];
                    $new_coa_id = $new_coa->id;
                    $old_coa_id = $loan_account->coa_id;
                    $old_coa_amount = JournalDetail::selectRaw('sum(debit) - sum(credit) as bal')->where('coa_id',$old_coa_id)->first()->bal;
                    if(is_null($old_coa_amount)) $old_coa_amount = 0;
                    $new_coa_amount = $old_coa_amount;
                    array_push($journal_arr, [$new_coa_id, $old_coa_amount, $desc_str, $old_coa_id, $old_coa_amount, $desc_str, $desc_str]);
                    record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);

                    // Add journal ( debit: sub_air_id, credit: air_id )
                    $journal_arr = [];
                    $new_air_id = $new_air->id;
                    $old_air_id = $loan_account->air_id;
                    $old_air_amount = JournalDetail::selectRaw('sum(debit) - sum(credit) as bal')->where('coa_id',$old_air_id)->first()->bal;
                    if(is_null($old_air_amount)) $old_air_amount = 0;
                    $new_air_amount = $old_air_amount;
                    array_push($journal_arr, [$new_air_id, $new_air_amount, $desc_str, $old_air_id, $old_air_amount, $desc_str, $desc_str]);
                    record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);

                    // Add journal ( debit: old_suspense_id, credit: new_suspense_id )
                    $journal_arr = [];
                    $new_sus_id = $new_int_sus->id;
                    $old_sus_id = $loan_account->sus_id;
                    $old_sus_amount = JournalDetail::selectRaw('sum(credit) - sum(debit) as bal')->where('coa_id',$old_sus_id)->first()->bal;
                    if(is_null($old_sus_amount)) $old_sus_amount = 0;
                    $new_sus_amount = $old_sus_amount;
                    array_push($journal_arr, [$old_sus_id, $old_sus_amount, $desc_str, $new_sus_id, $old_sus_amount, $desc_str, $desc_str]);
                    record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);

                    // Provision ( Sub-Standard -> Default )
                    $provision_amount = PROV_RATE_TO_DOUBTFUL * $loan_account->balance;
                    $journal_arr = [];
                    $desc_str .= ' - prov amount = '.$provision_amount;
                    $pat1 = '%'.substr($loan_account->acc_key,0,7).'%';
                    if(strpos($loan_account->acc_key,'<') !== false){
                        $pat2 = '%<%';
                    }elseif(strpos($loan_account->acc_key,'>') !== false){
                        $pat2 = '%>%';
                    }
                    $debit_acc = CoaCategory::select('*')->where('name', 'LIKE', 'Exp-Bad and Doubtful Debt%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE', $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                    $credit_acc = CoaCategory::select('*')->where('name', 'LIKE', '(Less) Specific Loan Loses%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE', $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                    array_push($journal_arr, [$debit_acc->id, $provision_amount, $desc_str, $credit_acc->id, $provision_amount, $desc_str, $desc_str]);
                    record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);

                    // Update loan_account
                    $loan_account->coa_id = $new_coa->id;
                    $loan_account->air_id = $new_air->id;
                    $loan_account->int_inc_id = $new_int->id;
                    $loan_account->sus_id = $new_int_sus->id;
                    $loan_account->status = $loan_account->status + 1;
                    $loan_account->save();
                    break;
                }
                case 4 : // Doubtful -> Loss Loan
                {
                    $sub_loan_account = $loan_account;
                    $loan_account->prefix = $key_pre[$loan_account->status]; //"Loss Loan "
                    // Create new COA for Doubtful
                    // COA
                    $pre_prefix = $static['client_loan_account_pre_prefix']['coa']; //"";
                    $new_coa = createLoanAccountCoa($loan_account, $pre_prefix)['coa'];
                    // AIR
                    $pre_prefix = $static['client_loan_account_pre_prefix']['air']; //"Air-";
                    $new_air = createLoanAccountCoa($loan_account, $pre_prefix)['coa'];
                    // Interest Income
                    $pre_prefix = $static['client_loan_account_pre_prefix']['interest']; //"Int-Inc-";
                    $new_int = createLoanAccountCoa($loan_account, $pre_prefix)['coa'];
                    // Income in suspense
                    $pre_prefix = $static['client_loan_account_pre_prefix']['suspense']; //'Int-In Sus-'
                    $new_int_sus = createLoanAccountCoa($sub_loan_account, $pre_prefix)['coa'];
                    // Add journal ( debit: sub_coa_id, credit: coa_id )
                    $desc_str = "provision " . $loan_account->loan_ref . "(" . $static['client_loan_account_status'][$loan_account->status] . " -> " . $static['client_loan_account_status'][$loan_account->status + 1] . ")";
                    $journal_arr = [];
                    $new_coa_id = $new_coa->id;
                    $old_coa_id = $loan_account->coa_id;
                    $old_coa_amount = JournalDetail::selectRaw('sum(debit) - sum(credit) as bal')->where('coa_id',$old_coa_id)->first()->bal;
                    if(is_null($old_coa_amount)) $old_coa_amount = 0;
                    $new_coa_amount = $old_coa_amount;
                    array_push($journal_arr, [$new_coa_id, $new_coa_amount, $desc_str, $old_coa_id, $old_coa_amount, $desc_str, $desc_str]);
                    record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);

                    // Add journal ( debit: sub_air_id, credit: air_id )
                    $journal_arr = [];
                    $new_air_id = $new_air->id;
                    $old_air_id = $loan_account->air_id;
                    $old_air_amount = JournalDetail::selectRaw('sum(debit) - sum(credit) as bal')->where('coa_id',$old_air_id)->first()->bal;
                    if(is_null($old_air_amount)) $old_air_amount = 0;
                    $new_air_amount = $old_air_amount;
                    array_push($journal_arr, [$new_air_id, $new_air_amount, $desc_str, $old_air_id, $old_air_amount, $desc_str, $desc_str]);
                    record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);

                    // Add journal ( debit: old_suspense_id, credit: new_sus_id )
                    $journal_arr = [];
                    $new_sus_id = $new_int_sus->id;
                    $old_sus_id = $loan_account->sus_id;
                    $old_sus_amount = JournalDetail::selectRaw('sum(debit) - sum(credit) as bal')->where('coa_id',$old_sus_id)->first()->bal;
                    if(is_null($old_sus_amount)) $old_sus_amount = 0;
                    $new_sus_amount = $old_sus_amount;
                    array_push($journal_arr, [$old_sus_id, $old_sus_amount, $desc_str, $new_sus_id, $old_sus_amount, $desc_str, $desc_str]);
                    record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);

                    // Provision ( Doubtful -> Loss Loan )
                    $provision_amount = PROV_RATE_TO_LOSS_LOAN * $loan_account->balance;
                    $journal_arr = [];
                    $desc_str .= ' - prov amount = '.$provision_amount;
                    $pat1 = '%'.substr($loan_account->acc_key,0,7).'%';
                    if(strpos($loan_account->acc_key,'<') !== false){
                        $pat2 = '%<%';
                    }elseif(strpos($loan_account->acc_key,'>') !== false){
                        $pat2 = '%>%';
                    }
                    $debit_acc = CoaCategory::select('*')->where('name', 'LIKE', 'Exp-Bad and Doubtful Debt%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE',  $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                    $credit_acc = CoaCategory::select('*')->where('name', 'LIKE', '(Less) Specific Loan Loses%')->where('name', 'LIKE', $pat1)->where('name', 'LIKE',  $pat2)->where('type', 6)->where('currency', $loan_account->currency)->first();
                    array_push($journal_arr, [$debit_acc->id, $provision_amount, $desc_str, $credit_acc->id, $provision_amount, $desc_str, $desc_str]);
                    record_journal_no_trans($loan_account, $record_date, $journal_arr, $branch_id, $user_id);

                    // Update loan_account
                    $loan_account->coa_id = $new_coa->id;
                    $loan_account->air_id = $new_air->id;
                    $loan_account->int_inc_id = $new_int->id;
                    $loan_account->sus_id = $new_int_sus->id;
                    $loan_account->status = $loan_account->status + 1;
                    $loan_account->save();
                    break;
                }
                default: {
                break;
                }
            }
        }
    }
    */

    if (!function_exists('convert_journal_detail')) {
        function convert_journal_detail($coa_all = array(), $coa_details = array(), $type = 7)
        {
            $coa_details = $coa_details;
            foreach($coa_all as $icoa){
                if(isset($coa_details[$icoa->id])){
                    if($icoa->type == $type){
                        if(isset($coa_details[$icoa->parent_id])){
                            $coa_details[$icoa->parent_id]["pre_debit"]  += $coa_details[$icoa->id]['pre_debit'];
                            $coa_details[$icoa->parent_id]["pre_credit"] += $coa_details[$icoa->id]['pre_credit'];
                            $coa_details[$icoa->parent_id]["cur_debit"]  += $coa_details[$icoa->id]['cur_debit'];
                            $coa_details[$icoa->parent_id]["cur_credit"] += $coa_details[$icoa->id]['cur_credit'];
                            $coa_details[$icoa->parent_id]["parent"]      = $icoa->parent_id;
                            $coa_details[$icoa->parent_id]["code"]        = $icoa->account_code;
                            $coa_details[$icoa->parent_id]["nbc_code"]    = $icoa->nbc_code;
                            $coa_details[$icoa->parent_id]["initial"]     = substr($icoa->account_code, 0, 1);
                            $coa_details[$icoa->parent_id]["branch"]      = $icoa->branch_code;
                            $coa_details[$icoa->parent_id]["currency"]    = $icoa->currency;
                            $coa_details[$icoa->parent_id]["type"]        = $icoa->type;
                            $coa_details[$icoa->parent_id]["symbol"]      = $icoa->symbol;
                        }else{
                            $coa_details[$icoa->parent_id]["pre_debit"]  = $coa_details[$icoa->id]['pre_debit'];
                            $coa_details[$icoa->parent_id]["pre_credit"] = $coa_details[$icoa->id]['pre_credit'];
                            $coa_details[$icoa->parent_id]["cur_debit"]  = $coa_details[$icoa->id]['cur_debit'];
                            $coa_details[$icoa->parent_id]["cur_credit"] = $coa_details[$icoa->id]['cur_credit'];
                            $coa_details[$icoa->parent_id]["parent"]     = $icoa->parent_id;
                            $coa_details[$icoa->parent_id]["code"]       = $icoa->account_code;
                            $coa_details[$icoa->parent_id]["nbc_code"]   = $icoa->nbc_code;
                            $coa_details[$icoa->parent_id]["initial"]    = substr($icoa->account_code, 0, 1);
                            $coa_details[$icoa->parent_id]["branch"]     = $icoa->branch_code;
                            $coa_details[$icoa->parent_id]["currency"]   = $icoa->currency;
                            $coa_details[$icoa->parent_id]["type"]       = $icoa->type;
                            $coa_details[$icoa->parent_id]["symbol"]     = $icoa->symbol;
                        }
                    }
                }
            }
            return $coa_details;
        }
    }

    if (!function_exists('convert_journal_detail_cu')) {
        function convert_journal_detail_cu($coa_all = array(), $coa_details = array(), $type = 7)
        {
            $coa_details = $coa_details;
            foreach($coa_all as $icoa){
                if(isset($coa_details[$icoa->id])){
                    if($icoa->type == $type){
                        if(isset($coa_details[$icoa->parent_id])){
                            $coa_details[$icoa->parent_id]["cur_debit"]  += $coa_details[$icoa->id]['cur_debit'];
                            $coa_details[$icoa->parent_id]["cur_credit"] += $coa_details[$icoa->id]['cur_credit'];
                            $coa_details[$icoa->parent_id]["parent"]      = $icoa->parent_id;
                            $coa_details[$icoa->parent_id]["code"]        = $icoa->account_code;
                            $coa_details[$icoa->parent_id]["nbc_code"]    = $icoa->nbc_code;
                            $coa_details[$icoa->parent_id]["initial"]     = substr($icoa->account_code, 0, 1);
                            $coa_details[$icoa->parent_id]["branch"]      = $icoa->branch_code;
                            $coa_details[$icoa->parent_id]["currency"]    = $icoa->currency;
                            $coa_details[$icoa->parent_id]["type"]        = $icoa->type;
                            $coa_details[$icoa->parent_id]["symbol"]      = $icoa->symbol;
                        }else{
                            $coa_details[$icoa->parent_id]["cur_debit"]  = $coa_details[$icoa->id]['cur_debit'];
                            $coa_details[$icoa->parent_id]["cur_credit"] = $coa_details[$icoa->id]['cur_credit'];
                            $coa_details[$icoa->parent_id]["parent"]     = $icoa->parent_id;
                            $coa_details[$icoa->parent_id]["code"]       = $icoa->account_code;
                            $coa_details[$icoa->parent_id]["nbc_code"]   = $icoa->nbc_code;
                            $coa_details[$icoa->parent_id]["initial"]    = substr($icoa->account_code, 0, 1);
                            $coa_details[$icoa->parent_id]["branch"]     = $icoa->branch_code;
                            $coa_details[$icoa->parent_id]["currency"]   = $icoa->currency;
                            $coa_details[$icoa->parent_id]["type"]       = $icoa->type;
                            $coa_details[$icoa->parent_id]["symbol"]     = $icoa->symbol;
                        }
                    }
                }
            }
            return $coa_details;
        }
    }
}

if (!function_exists('payment_method_list')) {
    /**
     * The Payment Method dropdown list, from tb_payment_methods.
     *
     * Adding an account used to mean editing config/static_data.php and shipping a release. The 31
     * accounts that lived there were copied into the table keeping their ids and labels -- the copy
     * was made from the config array itself rather than retyped, so every label is byte-identical
     * and the 219,684 receipts in tb_till_transaction matched on that text keep matching.
     *
     * config('static_data.payment_type') is still in the file and is still the fallback below, so a
     * missing or unreachable table cannot take a teller screen down. It is no longer the source of
     * truth: accounts are maintained through Settings > Bank Account.
     *
     * @param bool $include_inactive  true on the maintenance list, false everywhere else.
     */
    function payment_method_list($include_inactive = false)
    {
        try {
            $rows = \App\Models\PaymentMethod::orderBy('id');
            if (!$include_inactive) { $rows->where('active', 1); }
            $list = $rows->lists('label', 'id');
            if (!empty($list)) { return $list; }
        } catch (\Exception $e) {
            // fall through to the config copy
        }

        // Table missing, unreachable or empty -- the built-in list still takes payments.
        return config('static_data.payment_type');
    }
}
