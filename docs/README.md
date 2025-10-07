# PolyTrans Documentation

## Quick Start

- **[Installation](user-guide/INSTALLATION.md)** - Setup and configuration
- **[Interface Guide](user-guide/INTERFACE.md)** - Using the admin UI
- **[FAQ](user-guide/FAQ.md)** - Common questions and troubleshooting

## User Guide

- **[Installation](user-guide/INSTALLATION.md)** - Install and configure
- **[Interface](user-guide/INTERFACE.md)** - Admin UI overview
- **[FAQ](user-guide/FAQ.md)** - Troubleshooting

## Administrator

- **[Configuration](admin/CONFIGURATION.md)** - Settings reference
- **[Workflows](admin/WORKFLOW-TRIGGERING.md)** - Creating and managing workflows
- **[Workflow Logs](admin/WORKFLOW-LOGGING.md)** - Monitoring workflow execution

## Developer

- **[Development Setup](developer/DEVELOPMENT_SETUP.md)** - Local dev environment
- **[Architecture](developer/ARCHITECTURE.md)** - System design
- **[API Documentation](developer/API-DOCUMENTATION.md)** - REST API reference
- **[Contributing](developer/CONTRIBUTING.md)** - Contribution guidelines

## Quick Reference

### Translation Workflow
```
Edit Post → Translation Scheduler → Select Languages → Translate
```

### Common Paths
- **Admin:** Settings → Translation Settings
- **Workflows:** PolyTrans → Post-Processing  
- **Logs:** PolyTrans → Translation Logs

### API Endpoints
```
POST /wp-json/polytrans/v1/translate
GET  /wp-json/polytrans/v1/status/{task_id}
POST /wp-json/polytrans/v1/workflow/execute
```

## Support

- Check **[FAQ](user-guide/FAQ.md)** first
- Review **Translation Logs** for errors
- See **[ARCHITECTURE](developer/ARCHITECTURE.md)** for technical details
