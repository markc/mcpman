#!/bin/bash

LOG_FILE="storage/logs/laravel.log"
CLAUDE_ENDPOINT="http://localhost:3000/mcp"  # Adjust to your Claude Code MCP endpoint

echo "Starting Laravel log monitor..."
echo "Monitoring: $LOG_FILE"
echo "Sending errors to Claude at: $CLAUDE_ENDPOINT"

# Function to send error to Claude
send_to_claude() {
    local error="$1"
    echo "=== NEW ERROR DETECTED ==="
    echo "$error"
    echo "=========================="
    
    # Send to Claude via HTTP (adjust as needed)
    curl -X POST "$CLAUDE_ENDPOINT" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer YOUR_API_KEY" \
        -d "{
            \"method\": \"conversation\",
            \"params\": {
                \"messages\": [{
                    \"role\": \"user\",
                    \"content\": \"NEW LARAVEL ERROR:\\n\\n$error\\n\\nPlease analyze this error and provide fixes.\"
                }]
            }
        }" 2>/dev/null | jq -r '.result.content' 2>/dev/null || echo "Failed to contact Claude"
    
    echo ""
}

# Monitor the log file for new errors
tail -F "$LOG_FILE" 2>/dev/null | while read line; do
    if echo "$line" | grep -E "\.(ERROR|CRITICAL|EMERGENCY):" >/dev/null 2>&1; then
        # Collect the full error context
        error_block="$line"
        
        # Wait a moment to collect stack trace
        sleep 0.5
        
        # Send to Claude for analysis
        send_to_claude "$error_block"
    fi
done