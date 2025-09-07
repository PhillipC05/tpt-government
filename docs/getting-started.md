# 🚀 Getting Started with TPT Government Platform

Welcome to the TPT Government Platform! This guide will get you up and running in just 5 minutes, even if you've never deployed software before.

## 🎯 Quick Start Options

### Option 1: One-Click Docker (Easiest - 2 Minutes)

If you have Docker installed, this is the fastest way:

```bash
# Step 1: Download the platform
git clone https://github.com/your-org/tpt-gov-platform.git
cd tpt-gov-platform

# Step 2: Start everything automatically
./deploy.sh deploy

# Step 3: Open your browser
# Visit: http://localhost
```

**That's it!** Your government platform is now running.

### Option 2: Guided Setup (For Beginners)

Don't have Docker? No problem! Follow our step-by-step wizard:

#### Step 1: Download and Install

**Windows Users:**
1. Download the platform ZIP file from [GitHub Releases](https://github.com/your-org/tpt-gov-platform/releases)
2. Extract to `C:\tpt-platform`
3. Double-click `setup.exe`

**Mac Users:**
1. Download from [GitHub Releases](https://github.com/your-org/tpt-gov-platform/releases)
2. Open the DMG file
3. Drag TPT Platform to Applications folder

**Linux Users:**
```bash
# Using package manager
sudo apt install tpt-gov-platform  # Ubuntu/Debian
sudo dnf install tpt-gov-platform  # Fedora/RHEL
```

#### Step 2: Run the Setup Wizard

The setup wizard will guide you through:
- ✅ Database configuration (we handle this for you)
- ✅ Admin user creation
- ✅ Basic settings
- ✅ Security setup

#### Step 3: Access Your Platform

After setup completes:
- **Main Site**: http://localhost
- **Admin Panel**: http://localhost/admin
- **API Documentation**: http://localhost/api/docs

## 📋 What Happens During Setup

### Automatic Configuration
The platform automatically configures:

**🔒 Security**
- SSL certificates (for HTTPS)
- Firewall rules
- Security headers
- Admin password (randomly generated)

**💾 Database**
- MySQL database creation
- Tables and initial data
- User accounts and permissions

**⚙️ Services**
- Web server (Nginx/Apache)
- Background job processing
- Email sending capabilities
- File storage setup

**📊 Monitoring**
- Health check endpoints
- Basic monitoring dashboard
- Log file setup

## 🎛️ First Login

### Admin Access
1. Go to: http://localhost/admin
2. Username: `admin`
3. Password: Check the setup completion screen (or logs)

### Change Default Password
**Important:** Change the admin password immediately!

1. Login to admin panel
2. Go to Settings → Security
3. Change admin password
4. Set up two-factor authentication

## 🏗️ Understanding Your Platform

### Main Components

```
🌐 Public Website (http://localhost)
   ├── Home Page
   ├── Services Directory
   ├── Document Library
   ├── Contact Forms
   └── Citizen Portal

🔧 Admin Panel (http://localhost/admin)
   ├── Dashboard & Analytics
   ├── User Management
   ├── Content Management
   ├── Workflow Designer
   └── System Settings

📡 API Endpoints (http://localhost/api)
   ├── RESTful APIs
   ├── Third-party Integrations
   ├── Mobile App Support
   └── Webhook Endpoints
```

### Key Features Available Immediately

**📄 Document Management**
- Upload government forms and documents
- Automatic categorization with AI
- Version control and collaboration
- Public document library

**👥 User Management**
- Citizen registration and profiles
- Role-based permissions
- Bulk user import/export
- User activity tracking

**🔄 Workflow Automation**
- Pre-built approval workflows
- Custom form processing
- Automated notifications
- Task assignment and tracking

**📊 Reporting & Analytics**
- Real-time dashboards
- Citizen service usage stats
- Performance metrics
- Custom report builder

## 🔧 Basic Configuration

### 1. Organization Settings

```bash
# Access admin panel
Go to: http://localhost/admin

# Navigate to: Settings → Organization
- Set your agency name
- Upload logo and branding
- Configure contact information
- Set timezone and language
```

### 2. User Registration Setup

```bash
# Admin Panel → Settings → Registration
- Enable/disable public registration
- Set registration requirements
- Configure email verification
- Set up approval workflows
```

### 3. Email Configuration

```bash
# Admin Panel → Settings → Email
- SMTP server settings
- Email templates
- Notification preferences
- Bulk email capabilities
```

### 4. Security Settings

```bash
# Admin Panel → Settings → Security
- Password policies
- Session timeouts
- IP restrictions
- Audit logging levels
```

## 📚 Learning Resources

### 📖 User Guides
- **[Citizen Portal Guide](user/citizen-portal.md)** - How citizens use the platform
- **[Admin Quick Start](admin/quick-start.md)** - Essential admin tasks
- **[Workflow Designer](admin/workflows.md)** - Creating automated processes

### 🎥 Video Tutorials
- **[5-Minute Setup](https://youtube.com/watch?v=setup-video)** - Complete installation
- **[First Hour](https://youtube.com/watch?v=first-hour)** - Basic configuration
- **[Advanced Features](https://youtube.com/watch?v=advanced)** - Power user tips

### 💬 Community Support
- **Forum**: [community.tpt.gov](https://community.tpt.gov)
- **Live Chat**: Available in admin panel
- **Email Support**: support@tpt.gov

## 🚨 Troubleshooting

### Common Issues

**❌ "Port 80 already in use"**
```bash
# Stop other web servers
sudo systemctl stop apache2  # Linux
# Or change port in configuration
```

**❌ "Database connection failed"**
```bash
# Check if MySQL is running
sudo systemctl status mysql

# Reset database password
./deploy.sh reset-db
```

**❌ "Permission denied"**
```bash
# Fix file permissions
sudo chown -R www-data:www-data /var/www/tpt-platform
sudo chmod -R 755 /var/www/tpt-platform
```

**❌ "SSL certificate error"**
```bash
# Run SSL setup
./deploy.sh setup-ssl

# Or disable SSL for testing
# Edit .env file: APP_SSL=false
```

### Getting Help

1. **Check Logs**: `./deploy.sh logs`
2. **Run Diagnostics**: `./deploy.sh diagnose`
3. **Reset Setup**: `./deploy.sh reset`
4. **Contact Support**: support@tpt.gov

## 🔄 Next Steps

### Immediate Actions (First Day)
- [ ] Change default admin password
- [ ] Set up your organization branding
- [ ] Configure email settings
- [ ] Create your first user accounts
- [ ] Upload initial documents

### Week 1 Goals
- [ ] Set up basic workflows
- [ ] Configure user roles and permissions
- [ ] Create citizen registration forms
- [ ] Set up automated notifications
- [ ] Test all major features

### Month 1 Goals
- [ ] Integrate with existing systems
- [ ] Set up advanced security policies
- [ ] Create comprehensive documentation
- [ ] Train staff on platform usage
- [ ] Plan for scaling and performance

## 🎯 Success Metrics

Track these to ensure successful adoption:

**📈 User Engagement**
- Daily active users
- Form submissions per day
- Document downloads
- Service request completion rate

**⚡ Performance**
- Page load times (< 2 seconds)
- Uptime (> 99%)
- Error rates (< 1%)
- User satisfaction scores

**🔒 Security**
- Failed login attempts
- Security incidents
- Compliance audit results
- Data breach prevention

## 📞 Need Help?

### Quick Support Options
- **📧 Email**: support@tpt.gov
- **💬 Live Chat**: Available 9 AM - 5 PM EST
- **📚 Documentation**: [docs.tpt.gov](https://docs.tpt.gov)
- **🎥 Video Guides**: [YouTube Channel](https://youtube.com/tpt-gov)

### Professional Services
- **Implementation Consulting**: Custom setup and configuration
- **Training Workshops**: Hands-on training for your team
- **Integration Services**: Connect with existing government systems
- **Ongoing Support**: 24/7 technical support packages

---

## 🎉 Congratulations!

You've successfully deployed the TPT Government Platform! This is a major step toward modernizing your government services.

**Remember**: Start small, learn as you go, and scale up as you become comfortable with the platform.

**Need help?** We're here to support you every step of the way!

---

[← Back to README](../README.md) | [Admin Guide](admin/README.md) | [User Guide](user/README.md)
