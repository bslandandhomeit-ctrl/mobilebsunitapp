<table><thead><tr><th colspan="11">Posting transactions: {{ $from_date }} to {{ $to_date }}</th></tr><tr><th>Date</th><th>Posting ID</th><th>Customer account</th><th>Customer</th><th>Project</th><th>Unit</th><th>Method</th><th>Type</th><th>Amount</th><th>Principal</th><th>Interest</th></tr></thead><tbody>
@foreach($teller_transaction as $row)
<tr><td>{{ $row->posting_date }}</td><td>{{ $row->id }}</td><td>{{ $row->cus_acc }}</td><td>{{ $row->client_name }}</td><td>{{ $row->project_name }}</td><td>{{ $row->unit_code }}</td><td>{{ $row->methode }}</td><td>{{ $row->trans_type }}</td><td>{{ $row->amount }}</td><td>{{ $row->total_principal }}</td><td>{{ $row->total_interest }}</td></tr>
@endforeach
</tbody></table>