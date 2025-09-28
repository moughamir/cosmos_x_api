#!/bin/bash
set -e

# The path to your API's public directory
API_PUBLIC_PATH=~/domains/moritotabi.com/public_html/cosmos/public

echo "[+] Creating .htaccess file in the API's public directory..."

cat > "$API_PUBLIC_PATH/.htaccess" <<'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</IfModule>
EOF

echo "[+] .htaccess file created successfully."
echo "[+] You can now try accessing your API endpoints again."
echo "    - All products: https://moritotabi.com/cosmos/products"
echo "    - Search products: https://moritotabi.com/cosmos/products/search?q=t-shirt"
