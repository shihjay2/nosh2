@if ($portal == false)
	<p>You have new test results available from {{ $displayname }}.  Reply to this e-mail at {{ $email }} to create a secure account to view your results. After you establish an account, please go to {{ $patient_portal }} to view your results. Only authorized users will be able to access the results.</p>
@else
	<p>You have new test results available from {{ $displayname }}.  Please go to {{ $patient_portal }} to view your results. Only authorized users will be able to access the results.</p>
@endif
