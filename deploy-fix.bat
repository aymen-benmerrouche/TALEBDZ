@echo off
REM ============================================================
REM Deploy Fix to Render - Commit and Push
REM ============================================================

echo ============================================================
echo   TalebDZ - Deploying Fixed Dockerfile to GitHub
echo ============================================================
echo.

echo [STEP 1] Checking Git status...
git status
echo.

echo [STEP 2] Adding Dockerfile to Git...
git add Dockerfile
echo [SUCCESS] Dockerfile staged for commit
echo.

echo [STEP 3] Committing changes...
git commit -m "Fix: Remove docker/apache.conf dependency - use inline config"
if errorlevel 1 (
    echo [WARNING] No changes to commit or commit failed
    echo.
    echo Checking last commit...
    git log --oneline -1
    echo.
    echo If you see the fix commit above, the Dockerfile is already pushed.
    echo Otherwise, please check what went wrong.
    pause
    exit /b 0
)
echo [SUCCESS] Changes committed
echo.

echo [STEP 4] Pushing to GitHub (main branch)...
git push origin main
if errorlevel 1 (
    echo.
    echo [ERROR] Failed to push to GitHub!
    echo.
    echo Troubleshooting:
    echo   1. Check internet connection
    echo   2. Verify you have push access to the repository
    echo   3. Run: git remote -v (to check remote URL)
    echo   4. You may need to authenticate with GitHub
    echo.
    pause
    exit /b 1
)
echo.
echo [SUCCESS] Pushed to GitHub!
echo.

echo ============================================================
echo   DEPLOYMENT SUCCESSFUL!
echo ============================================================
echo.
echo Next Steps:
echo   1. Go to: https://dashboard.render.com/
echo   2. Select your TalebDZ service
echo   3. Click "Manual Deploy" -^> "Deploy latest commit"
echo   4. Monitor the build logs (should take 3-5 minutes)
echo.
echo Build should now succeed without the apache.conf error!
echo.
echo Verify on GitHub:
echo   https://github.com/aymen-benmerrouche/TALEBDZ/blob/main/Dockerfile
echo.
echo The file should NOT contain: COPY docker/apache.conf
echo.
pause
