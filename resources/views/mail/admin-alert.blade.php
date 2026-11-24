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
                <h1 style="margin: 0 0 24px; font-family: Outfit, Inter, Arial, Helvetica, sans-serif; font-size: 24px; font-weight: 600; line-height: 32px; color: #292928"> {{ $title }}</h1> @if($isDigest)
                @if(! empty($items['cleared']))
                <p style="margin: 0; font-weight: 600; line-height: 24px">{{ __('rxdesk.alerts.digest.cleared') }}</p>
                <p style="margin: 4px 0 0; line-height: 24px">{{ __('rxdesk.alerts.digest.cleared_line') }}</p>
                <ul style="margin: 8px 0 0; padding-left: 20px; padding-right: 20px; line-height: 24px"> @foreach($items['cleared'] as $cleared)
                  <li>{{ $cleared['title'] }}</li>
                  @endforeach
                </ul>
                @endif
                @if(! empty($items['bucket_accounts']['rows']))
                <p style="margin: 24px 0 0; font-weight: 600; line-height: 24px">{{ __('rxdesk.alerts.digest.bucket_accounts') }}</p>
                <ul style="margin: 8px 0 0; padding-left: 20px; padding-right: 20px; line-height: 24px"> @foreach($items['bucket_accounts']['rows'] as $row)
                  <li>{{ __('rxdesk.alerts.digest.bucket_accounts_line', ['network' => $row['network'], 'account' => $row['account'], 'owner' => $row['owner'], 'percent' => $row['percent'], 'calls' => $row['calls']]) }}</li>
                  @endforeach
                </ul>
                @endif
                @if(! empty($items['issues']))
                <p style="margin: 24px 0 0; font-weight: 600; line-height: 24px">{{ __('rxdesk.alerts.digest.issues') }}</p>
                <ul style="margin: 8px 0 0; padding-left: 20px; padding-right: 20px; line-height: 24px"> @foreach($items['issues'] as $issue)
                  <li>
                    @if($issue['url'])
                    <a href="{{ $issue['url'] }}">{{ $issue['title'] }}</a>
                    @else
                    {{ $issue['title'] }}
                    @endif
                  </li>
                  @endforeach
                </ul>
                @endif
                @if(! empty($items['failed_jobs']))
                <p style="margin: 24px 0 0; font-weight: 600; line-height: 24px">{{ __('rxdesk.alerts.digest.failed_jobs') }}</p>
                <p style="margin: 4px 0 0; line-height: 24px">{{ trans_choice('rxdesk.alerts.digest.failed_jobs_line', $items['failed_jobs']) }}</p>
                @endif
                @if($items['restore_drill']['due'])
                <p style="margin: 24px 0 0; font-weight: 600; line-height: 24px">{{ __('rxdesk.alerts.digest.restore_drill') }}</p>
                <p style="margin: 4px 0 0; line-height: 24px">
                  @if($items['restore_drill']['last_performed_at'])
                  {{ __('rxdesk.alerts.digest.restore_drill_line', ['date' => $items['restore_drill']['last_performed_at']]) }}
                  @else
                  {{ __('rxdesk.alerts.digest.restore_drill_never') }}
                  @endif
                </p>
                @endif
                @else
                @if($isResolved)
                <p style="margin: 0; line-height: 24px">
                  {{ __('rxdesk.alerts.resolved_body') }}
                </p>
                @else
                <p style="margin: 0; line-height: 24px">
                  {{ __('rxdesk.alerts.messages.' . $key->value, $context) }}
                </p>
                @endif
                @endif
                <p style="margin: 24px 0 0; font-size: 14px; line-height: 20px">
                  {{ __('rxdesk.alerts.sent_at', ['time' => $sentAt]) }}
                </p>
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