#!/usr/bin/env bash
# Runs one read-only SQL query on the dev database and prints the rows,
# for checking a journey's side effects. The transaction is read-only, so a
# write fails instead of changing the data.
#
#   db.sh "select id, status from posts where workspace_id = '<id>'"
source "$(dirname "$0")/env.sh"

if [ "$#" -ne 1 ]; then
    echo "usage: db.sh \"<select ...>\"" >&2
    exit 2
fi

compose exec -T pgsql psql -U postgres -d trypost -v ON_ERROR_STOP=1 -P pager=off \
    -c "set default_transaction_read_only = on;" -c "$1"
