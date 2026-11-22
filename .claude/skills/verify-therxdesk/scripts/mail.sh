#!/usr/bin/env bash
# Reads and clears the dev Mailpit inbox. The dev app sends through Mailpit
# (MAIL_HOST=mailpit), so every email it queues lands here.
#
#   mail.sh list [--to X] [--subject Y] [--limit N]
#   mail.sh latest [--to X] [--subject Y]
#   mail.sh wait [--to X] [--subject Y] [--timeout 60]
#   mail.sh show <id>
#   mail.sh clear
#
# Filters are case-insensitive substrings. `wait` polls every 2 seconds until a
# match arrives. `show` prints the plain-text body because callers need it.
source "$(dirname "$0")/env.sh"

ACTION="${1:-list}"
if [ "$#" -gt 0 ]; then
    shift
fi

TO=""
SUBJECT=""
LIMIT="25"
TIMEOUT="60"
ID=""

while [ "$#" -gt 0 ]; do
    case "$1" in
        --to)
            TO="${2:-}"
            shift 2
            ;;
        --subject)
            SUBJECT="${2:-}"
            shift 2
            ;;
        --limit)
            LIMIT="${2:-}"
            shift 2
            ;;
        --timeout)
            TIMEOUT="${2:-}"
            shift 2
            ;;
        *)
            if [ -z "$ID" ]; then
                ID="$1"
                shift
            else
                echo "unexpected arg: $1" >&2
                exit 1
            fi
            ;;
    esac
done

fetch_messages() {
    curl -fsS --max-time 10 "$MAILPIT_URL/api/v1/messages?limit=${1:-500}"
}

# filter <mode>: reads the list JSON and prints matching rows. `list` prints
# ID, recipients and subject; `latest` prints the newest ID and subject and
# exits 3 when nothing matches.
filter() {
    python3 -I -c '
import json, sys

mode = sys.argv[1]
to = sys.argv[2].lower()
subject = sys.argv[3].lower()
limit = int(sys.argv[4])

data = json.load(sys.stdin)
rows = []

for message in data.get("messages", []):
    recipients = [item.get("Address", "").lower() for item in (message.get("To") or [])]
    if to and not any(to in address for address in recipients):
        continue
    if subject and subject not in (message.get("Subject") or "").lower():
        continue
    rows.append(message)
    if len(rows) >= limit:
        break

if mode == "latest":
    if not rows:
        sys.exit(3)
    message = rows[0]
    print("%s\t%s" % (message.get("ID", ""), message.get("Subject", "")))
else:
    for message in rows:
        addresses = ", ".join(item.get("Address", "") for item in (message.get("To") or []))
        print("%s\t%s\t%s" % (message.get("ID", ""), addresses, message.get("Subject", "")))
' "$1" "$TO" "$SUBJECT" "$LIMIT"
}

case "$ACTION" in
    list)
        fetch_messages 500 | filter list || true
        ;;
    latest)
        if ! fetch_messages 500 | filter latest; then
            echo "no matching email" >&2
            exit 1
        fi
        ;;
    wait)
        deadline=$((SECONDS + TIMEOUT))
        while :; do
            if found="$(fetch_messages 500 | filter latest)"; then
                echo "$found"
                exit 0
            fi
            if [ "$SECONDS" -ge "$deadline" ]; then
                echo "no matching email after ${TIMEOUT}s" >&2
                exit 1
            fi
            sleep 2
        done
        ;;
    show)
        if [ -z "$ID" ]; then
            echo "usage: mail.sh show <id>" >&2
            exit 1
        fi
        encoded="$(python3 -I -c 'import sys, urllib.parse; print(urllib.parse.quote(sys.argv[1], safe=""))' "$ID")"
        curl -fsS --max-time 10 "$MAILPIT_URL/api/v1/message/$encoded" | python3 -I -c '
import json, sys
from html.parser import HTMLParser

class PlainText(HTMLParser):
    """The readable text of an HTML mail: tags dropped, blocks on their own line."""

    BLOCKS = ("br", "p", "div", "tr", "li", "h1", "h2", "h3", "table")

    def __init__(self):
        super().__init__()
        self.parts = []
        self.skipping = 0

    def handle_starttag(self, tag, attrs):
        if tag in ("style", "script"):
            self.skipping += 1
        if tag in self.BLOCKS:
            self.parts.append("\n")

    def handle_endtag(self, tag):
        if tag in ("style", "script") and self.skipping:
            self.skipping -= 1

    def handle_data(self, data):
        if not self.skipping:
            self.parts.append(data)

def plain_text(html):
    parser = PlainText()
    parser.feed(html)
    lines = [line.strip() for line in "".join(parser.parts).splitlines()]
    return "\n".join(line for line in lines if line)

message = json.load(sys.stdin)
addresses = ", ".join(item.get("Address", "") for item in (message.get("To") or []))
print("Subject: %s" % message.get("Subject", ""))
print("To: %s" % addresses)
print("Date: %s" % message.get("Date", ""))
print()
# The app sends HTML-only mail, so Text is usually empty: read the HTML then.
print(message.get("Text") or plain_text(message.get("HTML") or ""))
'
        ;;
    clear)
        curl -fsS --max-time 10 -X DELETE "$MAILPIT_URL/api/v1/messages" >/dev/null
        echo "cleared"
        ;;
    *)
        echo "usage: mail.sh list|latest|wait|show|clear [--to X] [--subject Y] [--limit N] [--timeout N]" >&2
        exit 2
        ;;
esac
