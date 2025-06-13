# Filament Admin Interface Guide

This guide covers the Filament v4.0 admin interface features and how to use the MCP integration through the web interface.

## Accessing the Admin Panel

### URL
```
http://localhost:8000/admin
```

### Navigation Structure
The admin panel includes the following main sections:
- **Dashboard** - Overview and quick actions
- **MCP Status** - Connection monitoring and statistics
- **Chat with Claude** - Interactive conversation interface
- **Datasets** - Data collection management
- **Documents** - Content management
- **API Keys** - Authentication management
- **MCP Connections** - Outgoing connection management

## Core Resource Management

### 1. Dataset Management

**Location**: Admin Panel → Datasets

**Features**:
- Create, read, update, delete operations
- Automatic slug generation from dataset name
- Schema definition with JSON validation
- Metadata storage for additional configuration
- Status tracking (active, archived, processing)
- User ownership and permissions

**Creating a Dataset**:
1. Navigate to Datasets
2. Click "New Dataset"
3. Fill in the form:
   - **Name**: Human-readable dataset name
   - **Description**: Optional description
   - **Type**: Select format (JSON, CSV, XML, YAML, Text)
   - **Schema**: Define data structure (optional)
   - **Metadata**: Additional configuration
4. Click "Create"

**Dataset Form Fields**:
```php
- Name: Required, auto-generates slug
- Type: Select from supported formats
- Status: Active, Archived, Processing
- Schema: KeyValue component for field definitions
- Metadata: KeyValue component for configuration
```

### 2. Document Management

**Location**: Admin Panel → Documents

**Features**:
- Rich content management with multiple formats
- Optional dataset association
- Full-text search capabilities
- Metadata support
- Automatic slug generation

**Creating a Document**:
1. Navigate to Documents
2. Click "New Document"
3. Fill in the form:
   - **Title**: Document title
   - **Dataset**: Associate with existing dataset (optional)
   - **Type**: Content format (text, JSON, markdown, HTML)
   - **Content**: Main document content
   - **Metadata**: Additional document data
4. Click "Create"

### 3. API Key Management

**Location**: Admin Panel → API Keys

**Features**:
- Automatic key generation with 'mcp_' prefix
- Granular permission system
- Rate limiting configuration
- Usage tracking
- Expiration management

**Creating an API Key**:
1. Navigate to API Keys
2. Click "New API Key"
3. Configure the key:
   - **Name**: Descriptive name for the key
   - **Permissions**: Set resource access levels
   - **Rate Limits**: Configure usage restrictions
   - **Expires At**: Optional expiration date
4. Click "Create"

**Default Permissions**:
```json
{
  "datasets": "read,write",
  "documents": "read,write",
  "connections": "read"
}
```

### 4. MCP Connection Management

**Location**: Admin Panel → MCP Connections

**Features**:
- Multiple transport types (HTTP, WebSocket, stdio)
- Authentication configuration
- Capability detection
- Connection status monitoring
- Error tracking

**Creating a Connection**:
1. Navigate to MCP Connections
2. Click "New MCP Connection"
3. Configure the connection:
   - **Name**: Connection identifier
   - **Endpoint URL**: Target Claude Code instance
   - **Transport Type**: Communication method
   - **Authentication**: Bearer token or other auth
   - **Capabilities**: Supported features
4. Click "Create"

## MCP Dashboard

**Location**: Admin Panel → MCP Status

The MCP Dashboard provides real-time monitoring and system overview.

### Dashboard Sections

#### 1. Statistics Overview
- **Total Connections**: Number of configured MCP connections
- **Active Connections**: Currently operational connections
- **Datasets**: Total data collections
- **Documents**: Total content items
- **API Keys**: Active authentication keys

#### 2. Connection Status Grid
For each MCP connection:
- **Status Indicator**: Green (active), Red (error), Gray (inactive)
- **Connection Details**: Name, transport type, endpoint
- **Last Connected**: Time since last successful connection
- **Error Information**: Most recent error message
- **Test Button**: Manual connection testing

#### 3. Recent Activity Timeline
- Connection status changes
- Dataset creation/updates
- Document additions
- System events

### Dashboard Actions

#### Test All Connections
1. Click "Test All Connections" in the header
2. Confirm the action
3. Wait for results
4. Review connection status updates

#### Test Individual Connection
1. Find the connection in the status grid
2. Click the "Test" button
3. View the result notification

#### Refresh Statistics
1. Click "Refresh" in the header
2. Dashboard updates with latest data

## MCP Conversation Interface

**Location**: Admin Panel → Chat with Claude

The conversation interface provides real-time interaction with Claude Code instances.

### Interface Components

#### 1. Connection Selection
- Dropdown to select active MCP connection
- Only shows connections with "active" status
- Automatically loads available tools when connection is selected

#### 2. Message Interface
- **Message Input**: Multi-line text area for user messages
- **Tool Selection**: Optional tool execution
- **Tool Arguments**: Key-value pairs for tool parameters
- **Send Button**: Submit message to Claude Code

#### 3. Conversation Display
- **User Messages**: Blue bubbles on the right
- **Assistant Responses**: Gray bubbles on the left
- **Tool Calls**: Yellow bubbles with tool information
- **Tool Results**: Green bubbles with execution results
- **Errors**: Red bubbles for error messages

#### 4. Available Tools Panel
- Grid view of all available tools from the selected connection
- Tool name, description, and parameters
- Automatically populated when connection is selected

### Using the Conversation Interface

#### Basic Conversation
1. Select an MCP connection from the dropdown
2. Type your message in the text area
3. Click "Send Message"
4. View the response in the conversation area

#### Tool Execution
1. Select a tool from the "Use Tool" dropdown
2. Fill in required arguments in the Key-Value component
3. Click "Call Tool"
4. View the tool execution result

#### Managing Conversations
- **Clear Conversation**: Removes all messages (confirmation required)
- **Refresh Tools**: Reloads available tools from the connection
- **Auto-scroll**: Conversation automatically scrolls to latest messages

### Example Tool Usage

#### Creating a Dataset via Tool
1. Select tool: "create_dataset"
2. Set arguments:
   ```
   name: "Sample Dataset"
   description: "Test dataset created via MCP"
   type: "json"
   ```
3. Click "Call Tool"
4. View creation confirmation

#### Searching Documents
1. Select tool: "search_documents"
2. Set arguments:
   ```
   query: "search term"
   dataset_id: 1
   ```
3. Click "Call Tool"
4. View search results

## Filament v4.0 Features

### Enhanced Form Components
- **KeyValue**: For metadata and configuration
- **Select with Search**: For relationship selection
- **Auto-slug Generation**: Real-time slug creation
- **Live Validation**: Immediate feedback on form fields

### Table Features
- **Advanced Filtering**: Multi-criteria filtering
- **Bulk Actions**: Operations on multiple records
- **Export Functionality**: Data export capabilities
- **Search**: Global and column-specific search

### Page Types
- **Resource Pages**: Standard CRUD operations
- **Custom Pages**: Dashboard and conversation interfaces
- **Modal Forms**: Quick actions without page navigation

### UI/UX Improvements
- **Dark Mode**: Consistent dark theme
- **Responsive Design**: Mobile-friendly interface
- **Loading States**: Progress indicators for long operations
- **Notifications**: Toast messages for user feedback

## Customization

### Theme Configuration
The admin panel uses Filament's default theme with:
- **Primary Color**: Amber
- **Dark Mode**: Enabled by default
- **Icons**: Heroicons outline style

### Navigation Customization
Navigation items are automatically generated from:
- Resource classes
- Page classes
- Custom navigation items

### Form Customization
All forms use Filament v4.0 Schema components:
- Consistent validation
- Real-time updates
- Accessibility compliance
- Mobile optimization

## Keyboard Shortcuts

### Global Shortcuts
- `Ctrl/Cmd + K`: Global search
- `Ctrl/Cmd + /`: Toggle sidebar
- `Escape`: Close modals

### Conversation Interface
- `Ctrl/Cmd + Enter`: Send message
- `Shift + Enter`: New line in message
- `Ctrl/Cmd + L`: Clear conversation

## Performance Tips

### Large Datasets
- Use pagination for large document lists
- Implement search filters to reduce result sets
- Consider archiving old datasets

### Connection Management
- Regularly test connections to ensure availability
- Monitor connection logs for performance issues
- Use connection pooling for high-frequency operations

### Browser Performance
- Keep conversation history reasonable (use clear function)
- Close unused browser tabs
- Use modern browsers for optimal performance

For troubleshooting interface issues, see the [Troubleshooting Guide](07_troubleshooting.md).