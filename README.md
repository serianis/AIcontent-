# Smart Content AI - WordPress Plugin

An advanced WordPress plugin that uses Google Gemini, Chatgpt and Claude API to automatically generate AI-powered articles with SEO optimization, hero images, and structured schema markup.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Scheduling](#scheduling)
- [Security](#security)
- [API Integration](#api-integration)
- [Logging](#logging)
- [Testing](#testing)
- [Sample Payloads](#sample-payloads)
- [Troubleshooting](#troubleshooting)

## Features

- **AI-Powered Content Generation**: Uses Google Gemini 2.0 Flash to generate high-quality articles
- **SEO Optimization**: Generates meta titles, descriptions, and JSON-LD schema markup
- **Hero Image Generation**: Optionally generates and uploads featured images via Imagen-3
- **Scheduled Publishing**: Queue topics for automated daily publishing with configurable caps
- **Activity Logging**: Comprehensive request/response logging with statistics
- **Security**: OpenSSL-based API key encryption and centralized nonce/capability verification
- **Queue Management**: FIFO topic queue with admin controls
- **Yoast Compatibility**: Supports Yoast SEO meta fields for seamless integration

## Installation

### Prerequisites

- PHP 7.4 or higher
- WordPress 5.0 or later
- OpenSSL support (usually enabled by default)
- Google Gemini API key with access to:
  - `gemini-2.0-flash-exp` model (or fallback to `gemini-1.5-pro`)
  - `imagen-3.0-generate-001` for image generation (optional)

### Installation Steps

1. **Upload Plugin Files**
   - Download or clone this repository into `/wp-content/plugins/smartcontentai/`
   
2. **Activate Plugin**
   - Go to WordPress Admin Dashboard
   - Navigate to Plugins > Installed Plugins
   - Find "AI content" and click Activate
   
3. **Configure API Key**
   - Go to smartcontentai menu in admin
   - Enter your Google Gemini API key (encrypted before storage)
   - Set language, tone, and default post status
   - Click Save Settings

## Configuration

### Admin Settings

Access these settings via **Admin Dashboard > smartcontentai**:

| Setting | Description | Default |
|---------|-------------|---------|
| Google Gemini API Key | Your API key for Gemini model | - |
| Article Language | Language for generated content | Greek |
| Tone | Writing tone (Professional/Casual/Journalistic) | Professional |
| Default Post Status | Draft or Publish | Draft |
| Daily Publish Cap | Maximum posts to auto-publish per day (0 = unlimited) | 0 |

### Option Keys (for programmatic access)

```php
// API Configuration
get_option( 'smartcontentai_api_key' );          // Encrypted
get_option( 'smartcontentai_language' );         // 'Greek', 'English', etc.
get_option( 'smartcontentai_tone' );             // 'Professional', 'Casual', 'Journalistic'
get_option( 'smartcontentai_post_status' );      // 'draft', 'publish'

// Scheduling & Queue
get_option( 'smartcontentai_daily_publish_cap' );   // Integer
get_option( 'smartcontentai_publish_queue' );       // Array of queued topics
get_option( 'smartcontentai_last_publish_date' );   // Last publish timestamp
```

## Scheduling

### Cron Events

The plugin uses WordPress's built-in cron system for scheduled publishing.

**Event**: `smartcontentai_daily_publish_event`
- **Schedule**: Daily (once per day)
- **Action**: Processes queued topics respecting daily publish caps

### Enabling Automatic Scheduling

```php
// In your theme's functions.php or custom plugin:
$scheduler = new smartcontentai\Core\Scheduler();
$scheduler->schedule_events();
```

### Queue Management

**Enqueue a Topic**:
```php
$scheduler = new smartcontentai\Core\Scheduler();
$scheduler->enqueue_topic( 'Sustainable Living Tips', 'sustainability' );
$scheduler->enqueue_topic( 'Healthy Breakfast Recipes', 'breakfast recipes' );
```

**Check Queue Status**:
```php
$status = $scheduler->get_schedule_status();
echo 'Next run: ' . $status['next_run'];
echo 'Queued topics: ' . $status['queue_count'];
echo 'Daily cap: ' . $status['daily_cap'];
```

**Get Queued Topics**:
```php
$topics = $scheduler->get_queued_topics();
foreach ( $topics as $topic ) {
    echo $topic['topic'] . ' - ' . $topic['queued_at'];
}
```

**Clear Queue**:
```php
$scheduler->clear_queue();
```

### Daily Publish Cap

Prevent excessive auto-publishing:

```php
$scheduler->set_daily_publish_cap( 3 ); // Max 3 posts per day
$cap = $scheduler->get_daily_publish_cap(); // Get current cap
```

**Note**: The cap only applies to scheduled publishing via cron, not manual generation.

## Security

### API Key Encryption

API keys are encrypted using OpenSSL AES-256-CBC:

```php
$security = new smartcontentai\Core\Security();

// Encrypt when saving
$encrypted = $security->encrypt_api_key( $plain_api_key );
update_option( 'smartcontentai_api_key', $encrypted );

// Decrypt when using
$plain_key = $security->decrypt_api_key( get_option( 'smartcontentai_api_key' ) );
```

### Capability & Nonce Verification

```php
$security = new smartcontentai\Core\Security();

// Verify permission and nonce in one call
$result = $security->verify_capability_and_nonce( 
    'manage_options', 
    'smartcontentai_gen_nonce' 
);

if ( is_wp_error( $result ) ) {
    wp_send_json_error( $result->get_error_message() );
}
```

### Sanitization Helpers

```php
$security = new smartcontentai\Core\Security();

// Sanitize individual values
$clean_text = $security->sanitize_text( $_POST['topic'] );
$clean_url = $security->sanitize_url( $_POST['url'] );
$clean_email = $security->sanitize_email( $_POST['email'] );

// Sanitize arrays
$clean_array = $security->sanitize_array( $_POST, 'text' );

// Sanitize with specific types
$clean_int = $security->sanitize_value( '123', 'int' );
$clean_bool = $security->sanitize_value( '1', 'boolean' );
```

## API Integration

### Google Gemini Text Generation

The plugin uses the **Gemini 2.0 Flash** model for optimal balance of speed and quality.

**Endpoint**: `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent`

**Request Configuration**:
```php
$prompt = "You are an expert SEO content writer...";
$model = 'gemini-2.0-flash-exp';
$body = array(
    'contents'         => array(
        array( 'parts' => array( array( 'text' => $prompt ) ) ),
    ),
    'generationConfig' => array(
        'temperature'      => 0.7,
        'responseMimeType' => 'application/json',
    ),
);
```

**Response Format**: JSON-decoded object
- `candidates[0].content.parts[0].text` contains the generated content

### Image Generation (Imagen-3)

Optional hero image generation via Imagen API.

**Fallback Behavior**: If image generation fails, the article is still created without an image.

## Logging

### Database Table: `wp_smartcontentai_logs`

| Column | Type | Description |
|--------|------|-------------|
| id | mediumint | Primary key |
| created_at | datetime | Log creation timestamp |
| request_payload | text | Full request payload (JSON) |
| request_hash | varchar(64) | SHA256 hash of payload |
| status | varchar(50) | 'success', 'error', 'pending' |
| response_excerpt | text | Summary or error message |
| post_id | mediumint | Associated WordPress post ID |

### Query Logs Programmatically

```php
$logger = new smartcontentai\Utils\Logger();

// Get recent logs
$logs = $logger->get_logs( 50 );

// Get by status
$successful = $logger->get_logs_by_status( 'success', 25 );
$errors = $logger->get_logs_by_status( 'error' );

// Get by post
$post_logs = $logger->get_logs_by_post_id( 123 );

// Get by date range
$start = '2024-01-01 00:00:00';
$end = '2024-01-31 23:59:59';
$monthly_logs = $logger->get_logs_by_date_range( $start, $end );

// Get statistics
$stats = $logger->get_logs_statistics();
echo 'Total logs: ' . $stats->total_logs;
echo 'Success rate: ' . ( $stats->successful / $stats->total_logs * 100 ) . '%';

// Cleanup old logs
$logger->clear_old_logs( 30 ); // Delete logs older than 30 days
```

### Log Display in Admin

Access logs via **Admin Dashboard > smartcontentai > Logs**

## Testing

### Running Tests

The plugin includes comprehensive PHPUnit tests.

**Setup PHPUnit**:
```bash
cd /path/to/smartcontentai
composer install  # Ensure PHPUnit is in vendor/bin/
```

**Run All Tests**:
```bash
vendor/bin/phpunit
```

**Run Specific Test Suite**:
```bash
vendor/bin/phpunit tests/SchedulerTest.php
vendor/bin/phpunit tests/SecurityTest.php
vendor/bin/phpunit tests/LoggerTest.php
```

### Test Coverage

#### Scheduler Tests
- Enqueue/dequeue topic functionality
- Queue count tracking
- Daily publish cap validation
- Admin schedule exposure
- No overlapping cron execution

#### Security Tests
- API key encryption/decryption
- Capability and nonce verification
- Text, email, and URL sanitization
- Array sanitization with type conversion

#### Logger Tests
- Request hash generation
- Log insertion with multiple payload types
- Query filters (by status, post_id, date range)
- Statistics aggregation
- Old log cleanup

## Sample Payloads

### Content Generation Request

```json
{
  "contents": [
    {
      "parts": [
        {
          "text": "You are an expert SEO content writer. Write a comprehensive WordPress article in Greek. Topic: 'Sustainable Living Tips'. Keyword: 'sustainability'. Tone: Professional. Requirements: 1. Title: Catchy, SEO friendly, under 60 chars. 2. Content: HTML format with <h2> and <h3> tags. 3. Meta: meta_title and meta_description. 4. Image Prompt: Photorealistic hero image description. OUTPUT FORMAT: Valid JSON with keys: 'title', 'content_html', 'meta_title', 'meta_description', 'image_prompt'."
        }
      ]
    }
  ],
  "generationConfig": {
    "temperature": 0.7,
    "responseMimeType": "application/json"
  }
}
```

### Expected Response Structure

```json
{
  "candidates": [
    {
      "content": {
        "parts": [
          {
            "text": "{\"title\": \"Sustainable Living in the Modern World\", \"content_html\": \"<h2>Introduction</h2><p>Sustainability...</p>\", \"meta_title\": \"Sustainable Living Tips | Expert Guide\", \"meta_description\": \"Learn practical tips for a sustainable lifestyle. Expert advice on eco-friendly living.\", \"image_prompt\": \"A modern eco-friendly home with solar panels and a green garden...\"}"
          }
        ]
      }
    }
  ]
}
```

### Programmatic Generation

```php
$post_generator = new smartcontentai\Generator\Post( 
    $api_client, 
    $image_generator, 
    $logger 
);

$post_id = $post_generator->create_post( 
    'Sustainable Living Tips',  // Topic
    'sustainability'            // Keyword (optional)
);

if ( is_wp_error( $post_id ) ) {
    echo 'Error: ' . $post_id->get_error_message();
} else {
    echo 'Created post #' . $post_id;
}
```

## Troubleshooting

### API Key Not Saving

**Issue**: API key field shows empty after saving
**Solution**: Check that OpenSSL is enabled on your server:
```php
// Add to functions.php to diagnose
if ( ! extension_loaded( 'openssl' ) ) {
    wp_die( 'OpenSSL extension is required' );
}
```

### Images Not Generating

**Issue**: Articles created without featured images
**Solution**: Verify that:
1. Your API key has Imagen-3 access
2. Image prompt is not empty in generated content
3. Check logs for image generation errors

### Scheduled Posts Not Publishing

**Issue**: Queue not processing
**Solution**: 
1. Verify WordPress cron is enabled: `define( 'DISABLE_WP_CRON', false );`
2. Check schedule status: `$scheduler->get_schedule_status()`
3. Ensure daily cap hasn't been reached: `$scheduler->get_posts_published_today()`
4. Check activity logs for errors

### Memory Issues with Large Requests

**Issue**: PHP fatal error with large image generations
**Solution**: Increase `memory_limit` in `wp-config.php`:
```php
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'WP_MAX_MEMORY_LIMIT', '512M' );
```

## Performance Optimization

### Recommended Settings

- **Daily Publish Cap**: 5-10 posts (depends on server resources)
- **Cron Frequency**: Daily (avoid hourly to prevent server load)
- **Log Retention**: Clear logs older than 90 days monthly
- **Batch Size**: Process 1-2 queue items per cron run

### Monitoring

```php
// Monitor via admin
$logger->get_logs_statistics();

// Monitor queue
$scheduler->get_schedule_status();

// Check next execution
$next_run = wp_next_scheduled( 'smartcontentai_daily_publish_event' );
```

## Support & Contributing

For issues, feature requests, or contributions, please refer to the plugin documentation or contact support.

## License

Licensed under the same license as WordPress (GPL v2 or later).

## Changelog

### Version 1.0.0
- Initial release
- Security enhancements with OpenSSL encryption
- Scheduler with queue management and daily caps
- Enhanced logging with request hashing and statistics
- Comprehensive test suite
- Full documentation

---

**Last Updated**: 2024
**Maintainer**: Texnologia
