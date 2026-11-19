#!/usr/bin/env bash
set -eu

font_directory="$(cd -- "$(dirname -- "$0")" && pwd)"
inter_source='https://raw.githubusercontent.com/rsms/inter/v4.1'
download_directory="$(mktemp -d)"
trap 'rm -rf -- "$download_directory"' EXIT

curl --fail --location --silent --show-error \
  "$inter_source/docs/font-files/InterVariable.woff2" \
  --output "$download_directory/inter-var.woff2"
curl --fail --location --silent --show-error \
  "$inter_source/LICENSE.txt" \
  --output "$download_directory/Inter-OFL.txt"

cp -- "$download_directory/inter-var.woff2" "$download_directory/Inter-OFL.txt" "$font_directory/"
