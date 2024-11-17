# Weather to Mastodon WordPress Plugin

A WordPress plugin that automatically posts weather updates from a clientraw.txt file to Mastodon at configurable intervals.

## Features

- Posts weather updates to Mastodon automatically
- Configurable posting intervals (hourly, every 2 hours, every 6 hours)
- Reads data from clientraw.txt format weather files
- Customizable post format and content
- Test posting functionality
- Detailed error reporting

## Installation

1. Upload the plugin files to `/wp-content/plugins/weather-to-mastodon`
2. Activate the plugin through the WordPress Plugins menu
3. Go to Settings > Weather to Mastodon to configure

## Configuration

### Required Settings
- **Clientraw.txt URL**: Full URL to your weather station's clientraw.txt file
- **Mastodon Instance URL**: Your Mastodon instance (e.g., https://mastodon.social)
- **Access Token**: Your Mastodon API access token
- **Post Title**: Title for your weather updates
- **Location**: Your weather station's location
- **URL**: Optional link to include in posts

### Getting Your Mastodon API Access Token

1. Log into your Mastodon instance
2. Go to Settings > Development
3. Create New Application
4. Grant 'write:statuses' permission
5. Copy the access token

## Usage

After configuration:
1. Use the "Test Post" button to verify your settings
2. The plugin will automatically post at your chosen interval
3. Posts include:
   - Temperature
   - Humidity
   - Wind speed and direction
   - Location
   - Optional URL
   - #weather hashtag

## Support

Created by Marcus Hazel-McGown (MM0ZIF)

## License

GPL v2 or later

