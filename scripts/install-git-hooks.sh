#!/bin/bash
# Install git hooks to enforce workflow scripts usage

echo "🔧 Installing git hooks to enforce workflow scripts..."

# Create hooks directory if it doesn't exist
mkdir -p .git/hooks

# Pre-push hook to remind about git-finish.sh
cat > .git/hooks/pre-push << 'EOF'
#!/bin/bash
# Pre-push hook to enforce git workflow scripts

echo ""
echo "⚠️  WORKFLOW REMINDER ⚠️"
echo ""
echo "Did you use the proper git workflow scripts?"
echo ""
echo "✅ Required workflow:"
echo "   1. ./scripts/git-start.sh [branch-name]"
echo "   2. [make your changes]"
echo "   3. ./scripts/git-finish.sh [commit-message]"
echo ""
echo "❌ Direct git push is discouraged as it bypasses:"
echo "   - Automatic code formatting (Laravel Pint)"
echo "   - PR creation and auto-merge"
echo "   - Branch cleanup and verification"
echo ""

# Check if we're on main branch
current_branch=$(git branch --show-current)
if [ "$current_branch" = "main" ]; then
    echo "🚫 ERROR: Direct push to main branch detected!"
    echo ""
    echo "Use git-finish.sh instead, which handles:"
    echo "   - Code formatting"
    echo "   - PR creation"
    echo "   - Proper merging"
    echo "   - Branch cleanup"
    echo ""
    echo "To bypass this check (not recommended):"
    echo "   git push --no-verify"
    echo ""
    exit 1
fi

# Allow push but show reminder
echo "Proceeding with push..."
echo "💡 Remember: Use git-finish.sh for proper workflow completion"
echo ""
EOF

# Make hook executable
chmod +x .git/hooks/pre-push

echo "✅ Git hooks installed successfully!"
echo ""
echo "📋 What was installed:"
echo "   - Pre-push hook: Reminds about proper workflow"
echo "   - Main branch protection: Prevents direct pushes to main"
echo ""
echo "🔧 To bypass hooks (emergency only): git push --no-verify"