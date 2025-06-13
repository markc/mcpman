#!/bin/bash
# Git finish script - format, commit, PR, and merge with cleanup

echo "🚀 Starting git finish workflow..."

# Check if we're on a feature branch
CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" = "main" ]; then
    echo "❌ Error: Cannot run git finish on main branch"
    exit 1
fi

echo "📝 Running Laravel Pint code formatter..."
vendor/bin/pint

# Check for changes after formatting
if [ -z "$(git status --porcelain)" ]; then
    echo "ℹ️  No changes to commit. Repository is clean."
    exit 0
fi

# Get list of changed files for smart commit message generation
CHANGED_FILES=$(git diff --name-only HEAD)
echo "📋 Changed files: $(echo "$CHANGED_FILES" | tr '\n' ' ')"

# Smart commit message generation based on changed files
if [ -n "$1" ]; then
    COMMIT_MSG="$1"
    echo "💬 Using provided commit message: $COMMIT_MSG"
else
    echo "🤖 Generating smart commit message..."
    if echo "$CHANGED_FILES" | grep -q "CLAUDE.md"; then
        COMMIT_MSG="docs: update project documentation"
    elif echo "$CHANGED_FILES" | grep -q "Settings.php"; then
        COMMIT_MSG="feat: update Settings page functionality"
    elif echo "$CHANGED_FILES" | grep -q "PasswordHelper.php"; then
        COMMIT_MSG="fix: improve password hashing compatibility"
    elif echo "$CHANGED_FILES" | grep -q "\.php$"; then
        FIRST_PHP_FILE=$(echo "$CHANGED_FILES" | grep "\.php$" | head -1 | sed 's/.*\///')
        COMMIT_MSG="feat: update $FIRST_PHP_FILE"
    elif echo "$CHANGED_FILES" | grep -q "\.sh$"; then
        COMMIT_MSG="feat: update workflow scripts"
    elif echo "$CHANGED_FILES" | grep -q "\.md$"; then
        COMMIT_MSG="docs: update documentation"
    elif echo "$CHANGED_FILES" | grep -q "\.js\|\.css\|\.vue"; then
        COMMIT_MSG="feat: update frontend assets"
    else
        FIRST_FILE=$(echo "$CHANGED_FILES" | head -1 | sed 's/.*\///')
        COMMIT_MSG="feat: update $FIRST_FILE"
    fi
    echo "💬 Generated commit message: $COMMIT_MSG"
fi

# Stage and commit changes
echo "📦 Staging and committing changes..."
git add .
git commit -m "$COMMIT_MSG

🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>"

# Push to remote
echo "⬆️  Pushing to remote..."
git push -u origin "$CURRENT_BRANCH"

# Generate PR title from commit message
PR_TITLE=$(echo "$COMMIT_MSG" | sed 's/^[^:]*: *//' | sed 's/.*/\u&/')

# Generate PR body with file list
PR_BODY="## Summary
$(echo "$COMMIT_MSG" | sed 's/^[^:]*: *//' | sed 's/.*/\u&/')

## Changes
$(echo "$CHANGED_FILES" | head -5 | sed 's/^/- /')

🤖 Generated with [Claude Code](https://claude.ai/code)"

# Create PR
echo "🔄 Creating pull request..."
PR_URL=$(gh pr create --title "$PR_TITLE" --body "$PR_BODY")

# Get PR number from URL
PR_NUMBER=$(echo "$PR_URL" | grep -o '[0-9]*$')

# Try auto-merge first, fallback to immediate merge
echo "🤝 Attempting to merge PR..."
if gh pr merge "$PR_NUMBER" --auto --squash --delete-branch 2>/dev/null; then
    echo "✅ Auto-merge enabled, waiting for completion..."
    
    # Wait for auto-merge to complete
    echo "⏳ Waiting for auto-merge to complete..."
    for i in {1..30}; do
        sleep 2
        PR_STATE=$(gh pr view "$PR_NUMBER" --json state --jq '.state')
        if [ "$PR_STATE" = "MERGED" ]; then
            echo "✅ PR auto-merged successfully"
            break
        fi
        echo -n "."
    done
    
    if [ "$PR_STATE" != "MERGED" ]; then
        echo ""
        echo "⚠️  Auto-merge taking longer than expected, proceeding with immediate merge..."
        gh pr merge "$PR_NUMBER" --squash --delete-branch
    fi
else
    echo "🔄 Auto-merge not available, proceeding with immediate merge..."
    gh pr merge "$PR_NUMBER" --squash --delete-branch
fi

# Verify PR was merged
PR_STATE=$(gh pr view "$PR_NUMBER" --json state --jq '.state')
if [ "$PR_STATE" != "MERGED" ]; then
    echo "❌ Error: PR was not merged successfully"
    echo "   Please check: $PR_URL"
    exit 1
fi

# Switch back to main and pull latest changes
echo "🔄 Switching to main and pulling latest..."
git checkout main
git pull origin main

# Verify the changes are in main
LATEST_COMMIT=$(git log --oneline -1 --grep="$COMMIT_MSG" | head -1)
if [ -z "$LATEST_COMMIT" ]; then
    echo "⚠️  Changes may not be fully synced yet, doing another pull..."
    sleep 3
    git pull origin main
fi

# Clean up local feature branch
echo "🧹 Cleaning up local feature branch..."
git branch -d "$CURRENT_BRANCH" 2>/dev/null || echo "ℹ️  Local branch already cleaned up"

# Clean up any lingering remote tracking branches
echo "🧹 Cleaning up remote tracking branches..."
git remote prune origin

# Final verification
echo "🔍 Final verification..."
LOCAL_BRANCHES=$(git branch | grep -v "main" | wc -l)
REMOTE_BRANCHES=$(git branch -r | grep -v "origin/main" | grep -v "origin/HEAD" | wc -l)

echo ""
echo "✅ Git finish workflow completed successfully!"
echo "🎉 Changes merged and branches cleaned up."
echo ""
echo "📊 Repository status:"
echo "  - Current branch: $(git branch --show-current)"
echo "  - Local feature branches: $LOCAL_BRANCHES"
echo "  - Remote feature branches: $REMOTE_BRANCHES"
echo "  - Latest commit: $(git log --oneline -1)"
echo ""
if [ "$LOCAL_BRANCHES" -gt 0 ] || [ "$REMOTE_BRANCHES" -gt 0 ]; then
    echo "⚠️  Some branches may still exist. Run 'git branch -a' to check."
else
    echo "✅ All feature branches successfully cleaned up!"
fi