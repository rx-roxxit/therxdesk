# Mailpit (the dev inbox)

Every email the dev app sends goes to Mailpit (`MAIL_HOST=mailpit`, SMTP 1025), and the UI at
<http://localhost:8025> shows them. Use this when a proof needs a real email (an alert, an invite, a
verification link), not only `Mail::assertQueued` in a test.

## Helper

```sh
$V/mail.sh list [--to X] [--subject Y] [--limit N]
$V/mail.sh latest [--to X] [--subject Y]
$V/mail.sh wait [--to X] [--subject Y] [--timeout 60]
$V/mail.sh show <id>
$V/mail.sh clear
```

- Filters are case-insensitive substrings on the recipient address and the subject.
- `list` prints `ID`, recipients and subject, newest first.
- `latest` prints the newest matching `ID` and subject, and exits 1 when nothing matches.
- `wait` polls every 2 seconds until a matching email arrives (60 seconds by default), then prints its
  `ID` and subject.
- `show` prints the subject, recipients, date and body, because callers need the body: the plain-text part, or the
  HTML part with its tags stripped when the mail has none (the app's own mail is HTML-only).
- `clear` empties the inbox. It also removes unrelated mail the dev stack sent earlier.

The script reads Mailpit at `MAILPIT_URL` (`VERIFY_MAILPIT_URL`, default `http://localhost:8025`).

A fresh checkout seeded from the root `.env.example` points mail at `127.0.0.1:2525`, not Mailpit: the running dev
`.env` must say `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025` (the Docker template
`docker/.env.docker.example` has the right values).

## Proof checklist

- `mail.sh clear`, trigger the email, then `mail.sh wait --to <address> --subject '<subject>'` returns
  an id within the timeout.
- `mail.sh show <id>` prints the body with the expected values, not a placeholder.
- Nothing is left over: `mail.sh list --subject '<subject>'` shows only the mail the run sent.
