@use('App\Models\Operator')
@php
    $operator = Operator::current();
@endphp
@if($operator !== null)
<p class="operator-footer" style="margin: 8px 0 0; font-size: 12px; line-height: 20px; color: #5a5a59;">
    {{ __('rxdesk.mail.footer.operator', ['name' => $operator->legal_name_latin, 'address' => $operator->address_latin]) }}
</p>
@endif
