@use('App\Enums\User\Locale')
@php
$direction = Locale::tryFrom(app()->getLocale())?->direction() ?? 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $direction }}" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <!--[if mso]>
  <noscript>
    <xml>
      <o:OfficeDocumentSettings xmlns:o="urn:schemas-microsoft-com:office:office">
        <o:PixelsPerInch>96</o:PixelsPerInch>
      </o:OfficeDocumentSettings>
    </xml>
  </noscript>
  <style>
    td,th,div,p,a,h1,h2,h3,h4,h5,h6 {font-family: "Segoe UI", sans-serif; mso-line-height-rule: exactly;}
  </style>
  <![endif]-->
  @if(isset($title))
  <title>{{ $title }}</title>
  @endif
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Outfit:wght@600&display=swap" rel="stylesheet" media="screen">
  <style>
    @media (max-width: 600px) {
      .sm-p-6 {
        padding: 24px !important
      }
    }
  </style>
</head>
<body style="margin: 0; width: 100%; background-color: #f7f6f3; padding: 0; -webkit-font-smoothing: antialiased; word-break: break-word">
  @if(isset($previewText))
  <div style="display: none">
    {{ $previewText }}
    &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847; &#8199;&#65279;&#847;
  </div>
  @endif
  <div role="article" aria-roledescription="email" aria-label="{{ $title }}" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <table role="presentation" style="width: 100%; background-color: #f7f6f3; font-family: Inter, Arial, Helvetica, sans-serif" cellpadding="0" cellspacing="0">
      <tr>
        <td align="center" style="padding-left: 16px; padding-right: 16px">
          <!--[if mso]>
      <table role="presentation" width="552" align="center"><tr><td>
      <![endif]-->
          <table role="presentation" align="center" style="width: 100%; max-width: 552px" cellpadding="0" cellspacing="0">
            <tr>
              <td style="padding-top: 32px; padding-bottom: 32px">
                <div style="text-align: center">
                  <a href="{{ config('app.url') }}" target="_blank" style="color: #18181b; font-size: 22px; font-weight: 700; line-height: 33px; text-decoration: none;">The RX Desk</a>
                </div>
              </td>
            </tr>
            <tr>
              <td align="{{ $direction === 'rtl' ? 'right' : 'left' }}" class="sm-p-6" style="border-radius: 12px; border: 1px solid #eae8e5; background-color: #ffffff; padding: 32px; font-size: 16px; line-height: 24px; color: #292928">
                <h1 style="margin: 0 0 24px; font-family: Outfit, Inter, Arial, Helvetica, sans-serif; font-size: 24px; font-weight: 600; line-height: 32px; color: #292928"> {{ __('rxdesk.support_handoff.title') }}</h1>
                <p style="margin: 0; white-space: pre-line; line-height: 24px">{{ $customerMessage }}</p>
                <div role="separator" style="line-height: 24px">&zwj;</div>
                <div style="margin-top: 16px; border-radius: 8px; background-color: #f7f6f3; padding: 16px">
                  <p style="margin: 0; font-size: 14px; line-height: 20px">
                    <strong>{{ __('rxdesk.support_handoff.email_label') }}:</strong> {{ $customerEmail }}
                  </p>
                  <p style="margin: 4px 0 0; font-size: 14px; line-height: 20px">
                    <strong>{{ __('rxdesk.support_handoff.account_label') }}:</strong> {{ $accountId }}
                  </p>
                  <p style="margin: 4px 0 0; font-size: 14px; line-height: 20px">
                    <strong>{{ __('rxdesk.support_handoff.plan_label') }}:</strong> {{ $plan }}
                  </p>
                  <p style="margin: 4px 0 0; font-size: 14px; line-height: 20px">
                    <strong>{{ __('rxdesk.support_handoff.status_label') }}:</strong> {{ $status }}
                  </p>
                  <p style="margin: 4px 0 0; font-size: 14px; line-height: 20px">
                    <strong>{{ __('rxdesk.support_handoff.locale_label') }}:</strong> {{ $locale }}
                  </p>
                  <p style="margin: 4px 0 0; font-size: 14px; line-height: 20px">
                    <strong>{{ __('rxdesk.support_handoff.page_label') }}:</strong> {{ $lastPage !== null ? $lastPage : '—' }}
                  </p>
                </div>
                <div role="separator" style="line-height: 24px">&zwj;</div>
                <p style="margin: 0; font-weight: 600; line-height: 24px">{{ __('rxdesk.support_handoff.transcript') }}</p>
                @foreach($transcript as $entry)
                <div style="margin-top: 16px">
                  <p style="margin: 0; font-size: 14px; font-weight: 600; line-height: 20px; color: #292928">{{ $entry['role'] }}</p>
                  <p style="margin: 0; white-space: pre-line; line-height: 24px">{{ $entry['content'] }}</p>
                </div>
                @endforeach
              </td>
            </tr>
            <tr>
              <td align="center" style="padding: 24px; text-align: center; font-size: 12px; line-height: 20px; color: #5a5a59">
                <p style="margin: 0 0 8px">
                  {!! str_replace(':brand', '<a href="'.e(config('app.url')).'" target="_blank" style="color: inherit; font-weight: 600; text-decoration: none;">The RX Desk</a>', e(__('mail.layout.tagline'))) !!}
                </p>
                @include('mail.partials.operator-footer') <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-left: auto; margin-right: auto; margin-top: 12px">
                  <tr>
                    <td style="padding-left: 4px; padding-right: 4px">
                      <a href="https://github.com/rx-roxxit/therxdesk" target="_blank">
                        <img src="{{ asset('/images/emails/social/github.png') }}" width="20" height="20" alt="GitHub" style="max-width: 100%; vertical-align: middle">
                      </a>
                    </td>
                  </tr>
                </table>
                <p style="margin: 12px 0 0">
                  &copy; {{ date('Y') }} The RX Desk
                </p>
              </td>
            </tr>
          </table>
          <!--[if mso]>
      </td></tr></table>
      <![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>