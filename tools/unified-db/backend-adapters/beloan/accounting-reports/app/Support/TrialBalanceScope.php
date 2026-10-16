<?php
namespace App\Support;

class TrialBalanceScope
{
    public static function branchForUser($user)
    {
        $branch = $user->get_branch()->first();
        return $branch ? $branch->branch_code : null;
    }

    public static function apply($query, $start, $end, $opening, $role, $ownBranch, $requestedBranch)
    {
        foreach (array($start, $end) as $date) {
            $parsed = \DateTime::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new \InvalidArgumentException('Invalid trial balance date.');
            }
        }
        if ($start > $end) throw new \InvalidArgumentException('Start date must precede end date.');
        $query->where('journal_detail.is_audit', '=', 1);
        if ($opening) {
            $query->where('journal_requiry.entry_date', '>', '2012-01-01')
                ->where('journal_requiry.entry_date', '<', $start);
        } else {
            $afterEnd = new \DateTime($end);
            $query->where('journal_requiry.entry_date', '>=', $start)
                ->where('journal_requiry.entry_date', '<', $afterEnd->modify('+1 day')->format('Y-m-d'));
        }
        $branch = in_array((int)$role, array(1, 2), true) ? $requestedBranch : $ownBranch;
        if (!in_array((int)$role, array(1, 2), true) && ($branch === null || $branch === '')) {
            throw new \InvalidArgumentException('A branch is required for this report.');
        }
        if ($branch !== null && $branch !== '') $query->where('journal_detail.branch_code', '=', $branch);
        return $query;
    }
}
