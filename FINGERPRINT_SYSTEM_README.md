# Fingerprint Content Scanning System

## Overview

The fingerprint system automatically scans all text content on websites where the HoverVid plugin is installed and stores this content in the PostgreSQL database for later video translation assignment.

## How It Works

### 1. Content Scanning Process

The plugin's JavaScript scanner (`hovervid-plugin/public/js/text-scanner.js`) automatically:

- **Scans all text elements** on the page (headings, paragraphs, links, buttons)
- **Generates unique fingerprints** for each text element using content hashing
- **Captures context information** about where each text element appears
- **Filters out code and unwanted content** (JavaScript, CSS, admin elements)
- **Combines related text elements** (text + nearby links with same styling)

### 2. Fingerprint Data Structure

Each scanned text element generates a fingerprint with:

```javascript
{
    text: "The actual text content",
    hash: "unique_content_hash", 
    context: "element_hierarchy → parent_element"
}
```

**Example:**
```javascript
{
    text: "Welcome to our website",
    hash: "abc123def456",
    context: "h1(heading) → div.header → body"
}
```

### 3. Automatic API Storage

The scanner automatically sends fingerprint data to the Laravel API:

- **Endpoint:** `POST /api/content`
- **Frequency:** After page load and when new content is detected
- **Deduplication:** Only new content is stored (based on hash)

### 4. Database Storage

Content is stored in the `content` table with these fields:

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Content hash (primary key) |
| `domain_id` | bigint | Reference to domains table |
| `user_id` | bigint | Reference to users table |
| `content_element` | text | Original content element |
| `text` | text | Clean text content |
| `context` | text | Element hierarchy context |
| `url` | text | Domain name |
| `video_url` | text | Assigned video URL (nullable) |
| `created_at` | timestamp | When content was first scanned |

## API Endpoints

### Store Fingerprint Data
```http
POST /api/content
Content-Type: application/json

{
    "domain_name": "example.com",
    "fingerprint_data": [
        {
            "text": "Welcome to our website",
            "hash": "abc123",
            "context": "h1(heading) → div.header"
        }
    ]
}
```

**Response:**
```json
{
    "success": true,
    "message": "Fingerprint data saved successfully",
    "data": {
        "domain_id": 1,
        "inserted_count": 5,
        "skipped_count": 2,
        "total_processed": 7
    }
}
```

### Retrieve Content
```http
GET /api/content?domain_name=example.com
```

**Response:**
```json
{
    "success": true,
    "content": [
        {
            "id": "abc123",
            "domain_id": 1,
            "content_element": "Welcome to our website",
            "text": "Welcome to our website",
            "context": "h1(heading) → div.header",
            "video_url": null,
            "url": "example.com"
        }
    ],
    "total_count": 1
}
```

## Plugin Integration

### JavaScript Functions

The scanner provides these global functions for debugging:

```javascript
// Manual scan and API submission
window.slvpScanner.scan();

// Auto-scan functionality
window.slvpScanner.autoScan();

// Send data to API
window.slvpScanner.sendToAPI(fingerprintData);

// Collect current fingerprint data
window.slvpScanner.collectData();

// Check if scanning is in progress
window.slvpScanner.isScanning();
```

### Automatic Scanning

The system automatically scans:

1. **On page load** - 3 seconds after DOM is ready
2. **On content changes** - When new elements are added via AJAX/JavaScript
3. **On navigation** - For single-page applications

### Content Filtering

The scanner automatically excludes:

- Admin bars and plugin elements
- JavaScript code and variables
- CSS styles and selectors
- Template syntax and placeholders
- Hidden or invisible elements
- Duplicate content

## Database Schema

### Content Table Structure

```sql
CREATE TABLE content (
    id VARCHAR(255) PRIMARY KEY,           -- Content hash
    domain_id BIGINT REFERENCES domains(id),
    user_id BIGINT REFERENCES users(id),
    content_element TEXT,                  -- Original content
    text TEXT,                            -- Clean text content
    context TEXT,                         -- Element context
    url TEXT,                            -- Domain name
    video_url TEXT,                      -- Assigned video (nullable)
    created_at TIMESTAMP DEFAULT NOW()
);
```

### Relationships

- **Content** belongs to **Domain** (domain_id)
- **Content** belongs to **User** (user_id)
- **Domain** belongs to **User** (user_id)

## Testing

### Test the API

Run the test script to verify the fingerprint API:

```bash
php test-fingerprint-api.php
```

### Check Database

Query the content table to see stored fingerprints:

```sql
SELECT id, text, context, created_at 
FROM content 
WHERE domain_id = (SELECT id FROM domains WHERE domain = 'your-domain.com')
ORDER BY created_at DESC;
```

### Browser Console

Open browser console on any page with the plugin to see:

- Fingerprint scanning progress
- API submission results
- Content processing logs

## Troubleshooting

### Common Issues

1. **No content being scanned**
   - Check if plugin is active
   - Verify JavaScript console for errors
   - Ensure domain is registered in database

2. **API errors**
   - Verify domain exists in `domains` table
   - Check Laravel logs for detailed errors
   - Ensure database connection is working

3. **Duplicate content**
   - System automatically deduplicates by hash
   - Check `skipped_count` in API response

### Debug Commands

```javascript
// Check current fingerprint data
console.log(window.slvpScanner.collectData());

// Force manual scan
window.slvpScanner.scan();

// Check scanning status
console.log(window.slvpScanner.isScanning());
```

## Next Steps

After content is scanned and stored:

1. **Video Assignment** - Assign sign language videos to content
2. **Translation Management** - Manage translations through admin interface
3. **Content Updates** - Handle content changes and updates
4. **Analytics** - Track content usage and translation requests 
