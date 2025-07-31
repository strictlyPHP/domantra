#!/bin/bash

set -euo pipefail

# 1. Ensure required tools are installed
if ! command -v jq &> /dev/null; then
  echo "❌ 'jq' is required but not installed."
  exit 1
fi

# 2. Get new version from user
if [ "$#" -ne 1 ]; then
  echo "❌ Usage: $0 <new-version>"
  exit 1
fi

NEW_VERSION="$1"
RELEASE_BRANCH="release/$NEW_VERSION"

# 3. Check if tag already exists remotely
if git ls-remote --tags origin | grep -q "refs/tags/$NEW_VERSION$"; then
  echo "❌ Remote tag '$NEW_VERSION' already exists. Aborting."
  exit 1
fi

# 4. Extract current version from composer.json
CURRENT_VERSION=$(jq -r '.version' composer.json)

if [[ "$CURRENT_VERSION" == "null" || -z "$CURRENT_VERSION" ]]; then
  echo "❌ Current version not found in composer.json"
  exit 1
fi

# 5. Create release branch
git checkout -b "$RELEASE_BRANCH"

# 6. Generate changelog based on Git tags
if git rev-parse "refs/tags/$CURRENT_VERSION" >/dev/null 2>&1; then
  LOG_RANGE="$CURRENT_VERSION..HEAD"
else
  echo "⚠️ Previous tag $CURRENT_VERSION not found. Generating full log."
  LOG_RANGE=""
fi

CHANGELOG=$(git log $LOG_RANGE --pretty=format:"- %s (%an)" --no-merges)
echo "Generating changelog from $LOG_RANGE"

# 7. Write changelog to changelog.txt
echo -e "Changelog for version $NEW_VERSION\n\n$CHANGELOG" > changelog.txt

# 8. Update composer.json with new version
tmpfile=$(mktemp)
jq --arg version "$NEW_VERSION" '.version = $version' composer.json > "$tmpfile" && mv "$tmpfile" composer.json

# 9. Commit and push changes
git add composer.json changelog.txt

make install
make analyze
make style
make check-coverage

git commit -m "chore: prepare release $NEW_VERSION"
git push -u origin "$RELEASE_BRANCH"

echo "✅ Release branch '$RELEASE_BRANCH' created"
echo "📄 Changelog written to changelog.txt"
