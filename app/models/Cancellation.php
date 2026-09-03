<?php

class Cancellation extends Model
{
    protected $table = 'cancellation_requests';

    public function dashboardData()
    {
        $params = [];
        $siteClause = Auth::siteClause('cr.site_id', $params);

        return [
            'policies' => $this->query(
                "SELECT * FROM cancellation_policies WHERE status='active' ORDER BY label"
            )->fetchAll(),
            'requests' => $this->query(
                "SELECT cr.*,cp.label,cp.stock_effects,cp.blocking_dependencies,cp.document_types,
                        s.code site_code,requester.name requester_name,approver.name approver_name,
                        reversal.reversal_number
                 FROM cancellation_requests cr
                 JOIN cancellation_policies cp ON cp.entity_type=cr.entity_type
                 JOIN sites s ON s.id=cr.site_id
                 JOIN users requester ON requester.id=cr.requested_by
                 LEFT JOIN users approver ON approver.id=cr.approved_by
                 LEFT JOIN cancellation_reversals reversal ON reversal.request_id=cr.id
                 WHERE 1=1{$siteClause}
                 ORDER BY cr.requested_at DESC,cr.id DESC LIMIT 200",
                $params
            )->fetchAll(),
        ];
    }
}
