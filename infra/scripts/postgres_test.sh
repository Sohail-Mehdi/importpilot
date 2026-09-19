#!/bin/bash
set -e
echo "Creating table and inserting data..."
docker exec importpilot-postgres psql -U importpilot -c "CREATE TABLE IF NOT EXISTS test_persist (id serial PRIMARY KEY, data text);"
docker exec importpilot-postgres psql -U importpilot -c "INSERT INTO test_persist (data) VALUES ('hello persistence');"

echo "Restarting postgres container..."
docker restart importpilot-postgres > /dev/null
sleep 3

echo "Verifying data persistence..."
RESULT=$(docker exec importpilot-postgres psql -U importpilot -t -c "SELECT data FROM test_persist LIMIT 1;")
if echo "$RESULT" | grep -q "hello persistence"; then
    echo "[OK] Postgres Persistence Verified"
else
    echo "[FAIL] Postgres Persistence Failed"
fi
