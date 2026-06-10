#!/bin/bash
# ============================================================
# Quick Deploy Fix Script for Linux/Mac
# ============================================================

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "============================================================"
echo "  TalebDZ - Deploy Fix to Render"
echo "============================================================"
echo ""

echo -e "${BLUE}[INFO]${NC} Checking Git status..."
git status
echo ""

echo -e "${BLUE}[INFO]${NC} Adding updated Dockerfile..."
git add Dockerfile
git add RENDER_DEPLOYMENT_FIX.md

echo ""
echo -e "${BLUE}[INFO]${NC} Committing changes..."
if git commit -m "Fix: Use inline Apache configuration for Render deployment"; then
    echo ""
    echo -e "${GREEN}[SUCCESS]${NC} Changes committed locally"
else
    echo ""
    echo -e "${YELLOW}[WARNING]${NC} Nothing to commit or commit failed"
    echo ""
    echo "Checking if files are already committed..."
    git log --oneline -1
    echo ""
    exit 0
fi

echo ""
echo -e "${BLUE}[INFO]${NC} Pushing to GitHub..."
if git push origin main; then
    echo ""
    echo "============================================================"
    echo -e "${GREEN}[SUCCESS]${NC} Deployment fix pushed to GitHub!"
    echo "============================================================"
    echo ""
    echo "What happens next:"
    echo "  1. Render will detect the new commit"
    echo "  2. Automatic rebuild will start (if auto-deploy is enabled)"
    echo "  3. Build should complete in 3-5 minutes"
    echo "  4. Your service will be live!"
    echo ""
    echo "To monitor the deployment:"
    echo "  1. Go to https://dashboard.render.com/"
    echo "  2. Select your service"
    echo "  3. Click 'Logs' to watch the build progress"
    echo ""
else
    echo ""
    echo -e "${RED}[ERROR]${NC} Failed to push to GitHub"
    echo ""
    echo "Please check:"
    echo "  - You have internet connection"
    echo "  - You have push access to the repository"
    echo "  - The remote is set correctly: git remote -v"
    echo ""
    exit 1
fi
