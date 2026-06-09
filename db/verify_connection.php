<?php
// Verify database connection status
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  TALEBDZ - DATABASE CONNECTION VERIFICATION                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// 1. Environment Check
echo "1️⃣  ENVIRONMENT CONFIGURATION:\n";
echo "   ─────────────────────────────────────────────────────\n";
echo "   • USE_REST_API: " . (getenv('USE_REST_API') ?: 'false') . "\n";
echo "   • SUPABASE_URL: " . (SUPABASE_URL ? '✅ Configured' : '❌ Missing') . "\n";
echo "   • SUPABASE_ANON_KEY: " . (SUPABASE_ANON_KEY ? '✅ Configured' : '❌ Missing') . "\n";
echo "   • SUPABASE_SERVICE_ROLE_KEY: " . (SUPABASE_SERVICE_ROLE_KEY ? '✅ Configured' : '❌ Missing') . "\n";
echo "   • DATABASE_URL: " . (DATABASE_URL ? '✅ Configured' : '❌ Missing') . "\n\n";

// 2. Test Supabase REST API Connection
echo "2️⃣  SUPABASE REST API CONNECTION:\n";
echo "   ─────────────────────────────────────────────────────\n";
try {
    $response = Supabase::get('/rest/v1/', [], true);
    $status = $response['_http_status'] ?? 0;
    
    if ($status === 200) {
        echo "   ✅ REST API Connection: WORKING\n";
        echo "   📡 Endpoint: " . SUPABASE_URL . "\n\n";
    } else {
        echo "   ❌ REST API Connection: FAILED (Status: $status)\n\n";
    }
} catch (Throwable $e) {
    echo "   ❌ REST API Connection: ERROR\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// 3. Test Ads Table
echo "3️⃣  ADS TABLE ACCESS:\n";
echo "   ─────────────────────────────────────────────────────\n";
try {
    $ads = ads_list(true);
    echo "   ✅ Can read ads table\n";
    echo "   📊 Found: " . count($ads) . " ads\n";
    
    if (count($ads) > 0) {
        echo "   📋 Fields: " . implode(', ', array_keys($ads[0])) . "\n";
        
        // Verify correct columns
        $hasLinkUrl = isset($ads[0]['link_url']);
        $hasImpressions = isset($ads[0]['impressions_count']);
        
        if ($hasLinkUrl && $hasImpressions) {
            echo "   ✅ Using CORRECT columns (link_url, impressions_count)\n";
        } else {
            echo "   ⚠️  Column verification:\n";
            echo "      - link_url: " . ($hasLinkUrl ? '✅' : '❌') . "\n";
            echo "      - impressions_count: " . ($hasImpressions ? '✅' : '❌') . "\n";
        }
    }
    echo "\n";
} catch (Throwable $e) {
    echo "   ❌ Cannot access ads table\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// 4. Test Videos Table
echo "4️⃣  VIDEOS TABLE ACCESS:\n";
echo "   ─────────────────────────────────────────────────────\n";
try {
    $videos = videos_list(true);
    echo "   ✅ Can read videos table\n";
    echo "   📊 Found: " . count($videos) . " videos\n";
    
    if (count($videos) > 0) {
        echo "   📋 Fields: " . implode(', ', array_keys($videos[0])) . "\n";
        
        // Verify correct columns
        $hasGoogleDriveUrl = isset($videos[0]['google_drive_url']);
        $hasViewsCount = isset($videos[0]['views_count']);
        
        if ($hasGoogleDriveUrl && $hasViewsCount) {
            echo "   ✅ Using CORRECT columns (google_drive_url, views_count)\n";
        }
    }
    echo "\n";
} catch (Throwable $e) {
    echo "   ❌ Cannot access videos table\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// 5. Test Write Operations
echo "5️⃣  WRITE OPERATIONS TEST:\n";
echo "   ─────────────────────────────────────────────────────\n";
try {
    // Try to create a test ad
    $testAd = [
        'title' => 'Connection Test Ad',
        'description' => 'Testing write access',
        'link_url' => 'https://test.com',
        'start_date' => date('c'),
        'end_date' => date('c', strtotime('+1 day')),
        'is_active' => true
    ];
    
    $adId = ads_create($testAd);
    
    if ($adId) {
        echo "   ✅ CREATE: Working (ID: $adId)\n";
        
        // Try to update
        $updated = ads_update($adId, ['title' => 'Updated Test Ad']);
        echo "   " . ($updated ? '✅' : '❌') . " UPDATE: " . ($updated ? 'Working' : 'Failed') . "\n";
        
        // Try to delete
        $deleted = ads_delete($adId);
        echo "   " . ($deleted ? '✅' : '❌') . " DELETE: " . ($deleted ? 'Working' : 'Failed') . "\n";
    } else {
        echo "   ❌ CREATE: Failed\n";
    }
    echo "\n";
} catch (Throwable $e) {
    echo "   ❌ Write operations failed\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// 6. Summary
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  SUMMARY                                                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$allGood = true;

if (getenv('USE_REST_API') === 'true') {
    echo "✅ Connection Method: Supabase REST API\n";
} else {
    echo "⚠️  Connection Method: Direct PostgreSQL\n";
}

if (SUPABASE_URL && SUPABASE_SERVICE_ROLE_KEY) {
    echo "✅ Credentials: Configured\n";
} else {
    echo "❌ Credentials: Missing\n";
    $allGood = false;
}

try {
    $testAds = ads_list(true);
    echo "✅ Ads Table: Accessible\n";
} catch (Throwable $e) {
    echo "❌ Ads Table: Not accessible\n";
    $allGood = false;
}

try {
    $testVideos = videos_list(true);
    echo "✅ Videos Table: Accessible\n";
} catch (Throwable $e) {
    echo "❌ Videos Table: Not accessible\n";
    $allGood = false;
}

echo "\n";

if ($allGood) {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  🎉 ALL SYSTEMS OPERATIONAL! DATABASE CONNECTION WORKING! 🎉  ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
} else {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  ⚠️  SOME ISSUES DETECTED - CHECK CONFIGURATION ABOVE         ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
}
