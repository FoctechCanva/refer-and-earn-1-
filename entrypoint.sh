#!/bin/bash
set -e

# Set webhook URL (replace with your Render URL)
if [ -n "$RENDER_EXTERNAL_URL" ]; then
    curl -s -X POST "https://api.telegram.org/bot${BOT_TOKEN}/setWebhook?url=${RENDER_EXTERNAL_URL}"
    echo "Webhook set to: ${RENDER_EXTERNAL_URL}"
fi

# Start Apache
exec apache2-foreground