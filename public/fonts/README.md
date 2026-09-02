# Self-hosted Fonts for AI+ (LSTS)

This directory should contain the following font files in WOFF2 format:

## Required Font Files

### Inter (Variable + Static)
- `inter-var.woff2` - Variable font (wght 100-900)
- `inter-400.woff2` - Regular
- `inter-500.woff2` - Medium
- `inter-600.woff2` - Semi-bold
- `inter-700.woff2` - Bold

### Fraunces (Variable + Static)
- `fraunces-var.woff2` - Variable font (opsz 9-144, wght 100-900, normal)
- `fraunces-var-italic.woff2` - Variable font (opsz 9-144, wght 100-900, italic)
- `fraunces-400.woff2` - Regular
- `fraunces-500.woff2` - Medium
- `fraunces-600.woff2` - Semi-bold
- `fraunces-700.woff2` - Bold

### IBM Plex Mono (Variable + Static)
- `ibm-plex-mono-var.woff2` - Variable font (wght 100-700, normal)
- `ibm-plex-mono-var-italic.woff2` - Variable font (wght 100-700, italic)
- `ibm-plex-mono-400.woff2` - Regular
- `ibm-plex-mono-500.woff2` - Medium
- `ibm-plex-mono-600.woff2` - Semi-bold
- `ibm-plex-mono-700.woff2` - Bold

## How to Download

### Option 1: google-webfonts-helper (Recommended)
1. Go to https://gwfh.mranftl.com/fonts/
2. Search for each font:
   - **Inter** → Select weights: 400, 500, 600, 700 → Modern browsers → Download
   - **Fraunces** → Select weights: 400, 500, 600, 700 → Modern browsers → Download
   - **IBM Plex Mono** → Select weights: 400, 500, 600, 700 → Modern browsers → Download
3. Extract WOFF2 files to this directory

### Option 2: Fontsource (NPM)
```bash
npm install @fontsource-variable/inter @fontsource-variable/fraunces @fontsource-variable/ibm-plex-mono
# Then copy from node_modules/@fontsource-variable/*/files/*.woff2 to public/fonts/
```

### Option 3: Direct from Google Fonts GitHub
- Inter: https://github.com/google/fonts/tree/main/ofl/inter
- Fraunces: https://github.com/google/fonts/tree/main/ofl/fraunces
- IBM Plex Mono: https://github.com/google/fonts/tree/main/ofl/ibmplexmono

Download the `.ttf` files and convert to WOFF2 using:
```bash
# Install fonttools
pip install fonttools brotli

# Convert TTF to WOFF2
pyftsubset input.ttf --flavor=woff2 --output-file=output.woff2
```

## Current Status
@font-face declarations are in `public/css/ai-plus.css`. Once you place the actual font files here, the fonts will load locally without Google Fonts CDN.
