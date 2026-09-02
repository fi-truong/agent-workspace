#!/bin/bash
# Run this script on your local machine (not in container) to download all font files
# Usage: chmod +x download-fonts.sh && ./download-fonts.sh

set -e

FONTS_DIR="$(dirname "$0")"
echo "Downloading fonts to: $FONTS_DIR"

# Function to download with fallback
download() {
    local url="$1"
    local output="$2"
    echo "Downloading $output..."
    if curl -L -f -o "$output" "$url"; then
        echo "  ✓ $output"
    else
        echo "  ✗ Failed: $output"
        return 1
    fi
}

# Inter - from jsDelivr (Fontsource)
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/inter@5.1.0/files/inter-latin-wght-normal.woff2" "$FONTS_DIR/inter-var.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/inter@5.1.0/files/inter-latin-400-normal.woff2" "$FONTS_DIR/inter-400.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/inter@5.1.0/files/inter-latin-500-normal.woff2" "$FONTS_DIR/inter-500.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/inter@5.1.0/files/inter-latin-600-normal.woff2" "$FONTS_DIR/inter-600.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/inter@5.1.0/files/inter-latin-700-normal.woff2" "$FONTS_DIR/inter-700.woff2"

# Fraunces - from jsDelivr (Fontsource)
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/fraunces@5.1.0/files/fraunces-latin-wght-normal.woff2" "$FONTS_DIR/fraunces-var.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/fraunces@5.1.0/files/fraunces-latin-wght-italic.woff2" "$FONTS_DIR/fraunces-var-italic.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/fraunces@5.1.0/files/fraunces-latin-400-normal.woff2" "$FONTS_DIR/fraunces-400.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/fraunces@5.1.0/files/fraunces-latin-500-normal.woff2" "$FONTS_DIR/fraunces-500.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/fraunces@5.1.0/files/fraunces-latin-600-normal.woff2" "$FONTS_DIR/fraunces-600.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/fraunces@5.1.0/files/fraunces-latin-700-normal.woff2" "$FONTS_DIR/fraunces-700.woff2"

# IBM Plex Mono - from jsDelivr (Fontsource)
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/ibm-plex-mono@5.1.0/files/ibm-plex-mono-latin-wght-normal.woff2" "$FONTS_DIR/ibm-plex-mono-var.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/ibm-plex-mono@5.1.0/files/ibm-plex-mono-latin-wght-italic.woff2" "$FONTS_DIR/ibm-plex-mono-var-italic.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/ibm-plex-mono@5.1.0/files/ibm-plex-mono-latin-400-normal.woff2" "$FONTS_DIR/ibm-plex-mono-400.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/ibm-plex-mono@5.1.0/files/ibm-plex-mono-latin-500-normal.woff2" "$FONTS_DIR/ibm-plex-mono-500.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/ibm-plex-mono@5.1.0/files/ibm-plex-mono-latin-600-normal.woff2" "$FONTS_DIR/ibm-plex-mono-600.woff2"
download "https://cdn.jsdelivr.net/npm/@fontsource-variable/ibm-plex-mono@5.1.0/files/ibm-plex-mono-latin-700-normal.woff2" "$FONTS_DIR/ibm-plex-mono-700.woff2"

echo ""
echo "✅ All fonts downloaded!"
echo "Files in $FONTS_DIR:"
ls -la "$FONTS_DIR"/*.woff2
