#!/bin/bash
# Complete development environment setup script

echo "🚀 Setting up TIKM development environment..."
echo ""

# Check prerequisites
echo "📋 Checking prerequisites..."

# Check PHP version
php_version=$(php -r "echo PHP_VERSION;")
if [[ $(echo "$php_version 8.3" | awk '{print ($1 >= $2)}') == 1 ]]; then
    echo "✅ PHP $php_version (required: 8.3+)"
else
    echo "❌ PHP $php_version is too old. Required: 8.3+"
    exit 1
fi

# Check PHP extensions
required_extensions=("mbstring" "xml" "ctype" "fileinfo" "json" "pdo" "tokenizer")
missing_extensions=()

for ext in "${required_extensions[@]}"; do
    if php -m | grep -q "^$ext$"; then
        echo "✅ PHP extension: $ext"
    else
        missing_extensions+=("$ext")
    fi
done

# Check mailparse (optional but recommended)
if php -m | grep -q "^mailparse$"; then
    echo "✅ PHP extension: mailparse (email processing)"
else
    echo "⚠️  PHP extension: mailparse (missing - email processing will be limited)"
fi

if [ ${#missing_extensions[@]} -ne 0 ]; then
    echo "❌ Missing required PHP extensions: ${missing_extensions[*]}"
    echo "Please install them and try again."
    exit 1
fi

# Check Composer
if command -v composer &> /dev/null; then
    echo "✅ Composer $(composer --version | cut -d' ' -f3)"
else
    echo "❌ Composer is not installed"
    exit 1
fi

# Check Node.js
if command -v node &> /dev/null; then
    node_version=$(node -v)
    echo "✅ Node.js $node_version"
else
    echo "❌ Node.js is not installed"
    exit 1
fi

# Check npm
if command -v npm &> /dev/null; then
    npm_version=$(npm -v)
    echo "✅ npm $npm_version"
else
    echo "❌ npm is not installed"
    exit 1
fi

echo ""
echo "🔧 Installing dependencies..."

# Install Composer dependencies
echo "📦 Installing PHP dependencies..."
composer install --no-progress

# Install NPM dependencies
echo "📦 Installing Node.js dependencies..."
npm install

echo ""
echo "⚙️  Setting up environment..."

# Copy environment file if it doesn't exist
if [ ! -f .env ]; then
    echo "📄 Creating .env file..."
    cp .env.example .env
    php artisan key:generate
else
    echo "✅ .env file already exists"
fi

echo ""
echo "🗄️  Setting up database..."

# Create database
php artisan migrate
echo "✅ Database migrated"

# Seed database
php artisan db:seed
echo "✅ Database seeded"

echo ""
echo "🎨 Building frontend assets..."
npm run build

echo ""
echo "🔐 Installing git workflow hooks..."
./scripts/install-git-hooks.sh

echo ""
echo "🎉 Setup complete!"
echo ""
echo "📋 Next steps:"
echo "   1. Start development server: composer dev"
echo "   2. Access application: http://127.0.0.1:8000"
echo "   3. Access admin panel: http://127.0.0.1:8000/admin"
echo ""
echo "👥 Test accounts:"
echo "   - Admin: admin@example.com / password"
echo "   - Agent: agent@example.com / password"
echo "   - Customer: customer@example.com / password"
echo ""
echo "🔄 Development workflow:"
echo "   - Start work: ./scripts/git-start.sh [feature-name]"
echo "   - Finish work: ./scripts/git-finish.sh 'commit message'"
echo ""
echo "📚 Documentation: http://127.0.0.1:8000/docs"