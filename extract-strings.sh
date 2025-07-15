#!/bin/bash

# PolyTrans Translation String Extraction Script
# Automatically extracts translatable strings from PHP and JavaScript files
# and updates the .pot template file

set -e

# Configuration
PLUGIN_NAME="PolyTrans"
PLUGIN_VERSION="1.1.0"
TEXT_DOMAIN="polytrans"
POT_FILE="languages/polytrans.pot"
PACKAGE_NAME="PolyTrans WordPress Plugin"
BUGS_EMAIL="https://github.com/polytrans/polytrans-wp-plugin/issues"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔧 PolyTrans Translation String Extraction${NC}"
echo "============================================="

# Check if gettext tools are available
if ! command -v xgettext &> /dev/null; then
    echo -e "${RED}❌ xgettext not found. Please install gettext tools${NC}"
    exit 1
fi

echo "🔍 Extracting translatable strings for PolyTrans plugin..."

# Check if xgettext is available
if ! command -v xgettext &> /dev/null; then
    echo "❌ xgettext not found. Please install gettext tools:"
    echo "   Ubuntu/Debian: sudo apt-get install gettext"
    echo "   CentOS/RHEL: sudo yum install gettext"
    echo "   macOS: brew install gettext"
    exit 1
fi

# Create temporary pot file
TEMP_POT="/tmp/polytrans-temp.pot"

# Extract strings from PHP files
echo "📄 Extracting from PHP files..."
find "${PLUGIN_DIR}" -name "*.php" -not -path "*/vendor/*" -not -path "*/tests/*" | \
xgettext \
    --language=PHP \
    --keyword=__ \
    --keyword=_e \
    --keyword=_x:1,2c \
    --keyword=_ex:1,2c \
    --keyword=_n:1,2 \
    --keyword=_nx:1,2,4c \
    --keyword=esc_attr__ \
    --keyword=esc_attr_e \
    --keyword=esc_attr_x:1,2c \
    --keyword=esc_html__ \
    --keyword=esc_html_e \
    --keyword=esc_html_x:1,2c \
    --keyword=_n_noop:1,2 \
    --keyword=_nx_noop:1,2,3c \
    --sort-output \
    --package-name="PolyTrans" \
    --package-version="1.1.0" \
    --msgid-bugs-address="https://github.com/polytrans/polytrans-wp-plugin/issues" \
    --copyright-holder="PolyTrans Team" \
    --from-code=UTF-8 \
    --files-from=- \
    --output="${TEMP_POT}"

# Update header with proper information
echo "📝 Updating .pot file header..."
cat > "${POT_FILE}" << 'EOF'
# PolyTrans Plugin Translation Template
# Copyright (C) 2025 PolyTrans Team
# This file is distributed under the same license as the PolyTrans plugin.
# FIRST AUTHOR <EMAIL@ADDRESS>, YEAR.
#
#, fuzzy
msgid ""
msgstr ""
"Project-Id-Version: PolyTrans 1.1.0\n"
"Report-Msgid-Bugs-To: https://github.com/polytrans/polytrans-wp-plugin/issues\n"
"POT-Creation-Date: $(date -u '+%Y-%m-%d %H:%M+0000')\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"
"Language-Team: LANGUAGE <LL@li.org>\n"
"Language: \n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"X-Generator: extract-strings.sh\n"
"X-Domain: polytrans\n"

EOF

# Append extracted strings (skip the auto-generated header)
tail -n +18 "${TEMP_POT}" >> "${POT_FILE}"

# Clean up
rm -f "${TEMP_POT}"

# Count strings
STRING_COUNT=$(grep -c "^msgid" "${POT_FILE}" || echo "0")

echo "✅ Translation extraction complete!"
echo "📊 Found ${STRING_COUNT} translatable strings"
echo "📁 Updated: ${POT_FILE}"
echo ""
echo "Next steps:"
echo "1. Review the updated .pot file"
echo "2. Update existing .po files with new strings"
echo "3. Add new translations as needed"
