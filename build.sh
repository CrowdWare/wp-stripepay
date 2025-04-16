#!/bin/bash

# Script to create a zip file of the WP_CrowdFundTime plugin
# This script should be run from the plugin's root directory

# Set variables
PLUGIN_NAME="wp-stripepay"
# Read current version using the original method
CURRENT_VERSION=$(grep "Version:" wp-stripepay.php | awk -F': ' '{print $2}' | tr -d '\r')

if [ -z "$CURRENT_VERSION" ]; then
  echo "Error: Could not find current version number in wp-stripepay.php"
  exit 1
fi
echo "Current version: $CURRENT_VERSION"

# --- Start: Increment and Update Version ---
# Increment the last part of the version number using awk
NEW_VERSION=$(echo $CURRENT_VERSION | awk -F. -v OFS=. '{$NF = $NF + 1;} 1')
echo "New version: $NEW_VERSION"

# Update the version in the header line of wp-stripepay.php using perl
perl -pi -e "s/^(\s*\*\s*Version:\s*)$CURRENT_VERSION/\${1}$NEW_VERSION/" wp-stripepay.php
if [ $? -ne 0 ]; then
    echo "Error: Failed to update header version in wp-stripepay.php"
    exit 1
fi


echo "Updated version in wp-stripepay.php to $NEW_VERSION"
# --- End: Increment and Update Version ---

# Set ZIP_NAME using the NEW version
VERSION=$NEW_VERSION # Use the incremented version for the zip file name
ZIP_NAME="${PLUGIN_NAME}-${VERSION}.zip"

# Create a temporary directory
TMP_DIR=$(mktemp -d)
if [ -z "$TMP_DIR" ]; then
    echo "Error: Failed to create temporary directory"
    exit 1
fi
echo "Creating temporary directory: $TMP_DIR"

# Copy all files to the temporary directory
# Important: Copy the MODIFIED wp-stripepay.php
echo "Copying plugin files..."
cp -R ./* "$TMP_DIR"

# Remove any development or unnecessary files
echo "Removing unnecessary files..."
# Make sure to remove the correct script name if it was changed from create-zip.sh
rm -rf "$TMP_DIR/build.sh"
rm -rf "$TMP_DIR/.git"
rm -rf "$TMP_DIR/.gitignore"
rm -rf "$TMP_DIR/node_modules" # If you ever use npm
rm -rf "$TMP_DIR/package-lock.json" # If you ever use npm
rm -rf "$TMP_DIR/composer.lock" # If you ever use composer
rm -rf "$TMP_DIR/vendor" # If you ever use composer
rm -rf "$TMP_DIR/.DS_Store"
find "$TMP_DIR" -name ".DS_Store" -delete
find "$TMP_DIR" -path '*/.git/*' -delete # Remove any nested .git folders if submodules are used

# Create the zip file inside the temporary directory
echo "Creating zip file: $ZIP_NAME"
cd "$TMP_DIR"
zip -r "$ZIP_NAME" ./* -x "*.git*" "*node_modules*" "*.DS_Store*" "*.zip"
cd - > /dev/null # Go back to original directory

# Move the zip file from the temporary directory to the current directory
echo "Moving zip file to current directory..."
mv "$TMP_DIR/$ZIP_NAME" .

# Clean up
echo "Cleaning up temporary directory: $TMP_DIR"
rm -rf "$TMP_DIR"

echo "Done! Created $ZIP_NAME"
