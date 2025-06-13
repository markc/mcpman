#!/bin/bash
# Git cleanup script - removes merged feature branches and orphaned remotes

echo "🧹 Git cleanup - removing merged branches and orphaned remotes..."

# Get current branch
CURRENT_BRANCH=$(git branch --show-current)
echo "📍 Current branch: $CURRENT_BRANCH"

# Switch to main if not already there
if [ "$CURRENT_BRANCH" != "main" ]; then
    echo "🔄 Switching to main branch..."
    git checkout main
fi

# Pull latest changes
echo "⬇️  Pulling latest changes from main..."
git pull origin main

# List all branches for review
echo ""
echo "📋 Current branch status:"
echo "Local branches:"
git branch
echo ""
echo "Remote branches:"
git branch -r
echo ""

# Clean up merged local branches
echo "🧹 Cleaning up merged local branches..."
MERGED_BRANCHES=$(git branch --merged main | grep -v "main" | grep -v "\*" | tr -d ' ')
if [ -n "$MERGED_BRANCHES" ]; then
    echo "Found merged branches to delete:"
    echo "$MERGED_BRANCHES"
    echo "$MERGED_BRANCHES" | xargs -r git branch -d
    echo "✅ Merged local branches deleted"
else
    echo "ℹ️  No merged local branches to delete"
fi

# Clean up remote tracking branches
echo "🧹 Cleaning up stale remote tracking branches..."
git remote prune origin

# Option to force delete unmerged branches (with confirmation)
UNMERGED_BRANCHES=$(git branch --no-merged main | grep -v "main" | grep -v "\*" | tr -d ' ')
if [ -n "$UNMERGED_BRANCHES" ]; then
    echo ""
    echo "⚠️  Found unmerged local branches:"
    echo "$UNMERGED_BRANCHES"
    echo ""
    read -p "Do you want to force delete these unmerged branches? (y/N): " -r
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "$UNMERGED_BRANCHES" | xargs -r git branch -D
        echo "✅ Unmerged local branches force deleted"
    else
        echo "ℹ️  Keeping unmerged branches"
    fi
fi

# Clean up orphaned remote branches (those that don't exist on remote anymore)
echo "🧹 Checking for orphaned remote branches..."
REMOTE_BRANCHES=$(git branch -r | grep -v "origin/HEAD" | grep -v "origin/main" | sed 's/origin\///' | tr -d ' ')
if [ -n "$REMOTE_BRANCHES" ]; then
    echo "Found remote feature branches:"
    echo "$REMOTE_BRANCHES"
    echo ""
    read -p "Do you want to delete these remote branches? (y/N): " -r
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        for branch in $REMOTE_BRANCHES; do
            echo "Deleting remote branch: $branch"
            git push origin --delete "$branch" 2>/dev/null || echo "  (already deleted or protected)"
        done
        git remote prune origin
        echo "✅ Remote branches cleaned up"
    else
        echo "ℹ️  Keeping remote branches"
    fi
fi

# Final status
echo ""
echo "🔍 Final repository status:"
echo "Local branches:"
git branch
echo ""
echo "Remote branches:"
git branch -r
echo ""
echo "✅ Git cleanup completed!"