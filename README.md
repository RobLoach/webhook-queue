# Webhook Queue

Captures webhook calls, and caches them into an RSS queue. The queue is cleared when the RSS feed is retrieved. This is not the best practice around webhooks, but can have its use cases.

## Usage

1. Set up a `.env` file with the configuration provided below
2. Create an empty `.webhook` file to store the webhook data
3. Have clients make webhook calls to `/webhook?token=abc123`
4. Have your application retrieve all webhook call data as an RSS feed by requesting `/queue?server_key=abc123`

## Configuration

The configuration is set through environment variables, which can be provided through a `.env` file:

```
# The server's key. Used to retrieve the RSS feed.
SERVER_KEY=abc123

# Expected client token used when making webhook calls.
TOKEN=abc123

# Whether to enable or disable debug mode.
DEBUG=false

# The file location where webhook data will be kept.
FILE=.webhooks

# The expected base URL for where webhook queue is hosted.
HOMEPAGE="https://example.com"

# The title of the application.
TITLE=Webhook Queue

# The description for the application.
DESCRIPTION=Captures webhook calls, and caches them into an RSS queue.
```

## License

[MIT](LICENSE)
