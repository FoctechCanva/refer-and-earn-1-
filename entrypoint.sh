#!/bin/bash
set -e

# Set Telegram webhook using Render's provided URL
if [ -n "$RENDER_EXTERNAL_URL" ] && [ -n "$BOT_TOKEN" ]; then
    echo "Setting Telegram webhook to: ${RENDER_EXTERNAL_URL}"
    curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook?url=${RENDER_EXTERNAL_URL}" || true
fi

# Start Apache
exec apache2-foreground
