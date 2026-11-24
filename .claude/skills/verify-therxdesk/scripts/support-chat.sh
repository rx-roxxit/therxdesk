#!/usr/bin/env bash
# Switches the Support chat on for the fixture and can fill its last
# conversation to the 20-user-question cap, so the sheet and its limit copy can be
# driven in the browser. The switch is an Availability row. This helper writes it
# through the app's own service in a one-off container; the internal API also
# exposes PATCH /availability/service:support_chat.
#
#   support-chat.sh up     switch the chat on
#   support-chat.sh limit  fill the fixture's last conversation to 20 user questions
#   support-chat.sh clear  switch the chat off and delete its conversations
#
# The chat needs app access: run complimentary.sh up first.
source "$(dirname "$0")/env.sh"

FIXTURE="$(dirname "$0")/fixture.sh"

json_field() {
    python3 -I -c '
import json, sys
data = json.load(sys.stdin)
for key in sys.argv[1].split("."):
    data = data[key]
print(data)
' "$1"
}

user_id() {
    "$FIXTURE" show | json_field userId
}

tinker() {
    compose exec -T -u app app php artisan tinker --execute "$1"
}

case "${1:-up}" in
    up)
        tinker "
app(App\Support\Availability\Availability::class);
app(App\Support\SupportChat\SupportChatGate::class);
app(App\Support\Availability\Availability::class)->set(App\Support\SupportChat\SupportChatGate::AVAILABILITY_KEY, true);
echo 'chat on', PHP_EOL;
" | tail -1
        ;;
    limit)
        tinker "
\$user = App\Models\User::find('$(user_id)');
\$conversation = App\Models\SupportConversation::query()->where('user_id', \$user->id)->latest('last_message_at')->first();

if (\$conversation === null) {
    \$conversation = App\Models\SupportConversation::query()->create([
        'account_id' => \$user->account_id,
        'user_id' => \$user->id,
        'last_message_at' => now(),
    ]);
}

\$conversation->messages()->where('role', 'user')->delete();

foreach (range(1, 20) as \$index) {
    \$conversation->messages()->create(['role' => 'user', 'content' => \"Question {\$index}\"]);
}

\$conversation->forceFill(['last_message_at' => now()])->save();
echo \$conversation->id, PHP_EOL;
" | tail -1
        ;;
    clear)
        tinker "
\$user = App\Models\User::find('$(user_id)');
app(App\Support\Availability\Availability::class)->set(App\Support\SupportChat\SupportChatGate::AVAILABILITY_KEY, false);
App\Models\SupportConversation::query()->where('user_id', \$user->id)->delete();
echo 'chat cleared', PHP_EOL;
" | tail -1
        ;;
    *)
        echo "usage: support-chat.sh up|limit|clear" >&2
        exit 2
        ;;
esac
