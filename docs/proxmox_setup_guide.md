# Proxmox Platform Setup Guide

This guide provides comprehensive instructions for setting up and configuring Proxmox VE clusters for use with MCPman's automated development environment provisioning.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Proxmox VE Installation](#proxmox-ve-installation)
3. [Cluster Configuration](#cluster-configuration)
4. [API Setup and Authentication](#api-setup-and-authentication)
5. [Storage Configuration](#storage-configuration)
6. [Network Configuration](#network-configuration)
7. [Templates and Images](#templates-and-images)
8. [MCPman Integration](#mcpman-integration)
9. [Security Hardening](#security-hardening)
10. [Monitoring and Maintenance](#monitoring-and-maintenance)
11. [Troubleshooting](#troubleshooting)

## Prerequisites

### Hardware Requirements

**Minimum per node:**
- CPU: 4 cores (8 recommended)
- RAM: 8GB (16GB+ recommended)
- Storage: 100GB SSD (500GB+ recommended)
- Network: 1Gbps Ethernet (10Gbps recommended for production)

**Recommended cluster setup:**
- 3+ nodes for high availability
- Shared storage (Ceph, NFS, or iSCSI)
- Dedicated network interfaces for cluster communication
- UPS or reliable power source

### Software Requirements

- Proxmox VE 8.0+ (latest stable version recommended) 
- Debian 13 Trixie base system (includes PHP 8.4 and Dovecot 2.4.1 natively)
- Internet connectivity for package updates
- Valid domain name or IP addresses for cluster nodes

## Proxmox VE Installation

### 1. Download and Create Installation Media

```bash
# Download the latest Proxmox VE ISO
wget https://www.proxmox.com/en/downloads/proxmox-virtual-environment/iso
# Create bootable USB or burn to DVD
```

### 2. Install Proxmox VE

1. Boot from installation media
2. Select "Install Proxmox VE (Graphical)"
3. Accept license agreement
4. Configure target hard disk
5. Set country, timezone, and keyboard layout
6. Configure network (static IP recommended)
7. Set root password and email
8. Complete installation and reboot

### 3. Post-Installation Configuration

```bash
# Update system packages
apt update && apt upgrade -y

# Install additional packages
apt install -y curl wget gnupg2 software-properties-common

# Configure APT sources (remove enterprise repo if no subscription)
echo "deb http://download.proxmox.com/debian/pve bookworm pve-no-subscription" > /etc/apt/sources.list.d/pve-no-subscription.list
# Comment out or remove enterprise repository
sed -i 's/^deb/#deb/' /etc/apt/sources.list.d/pve-enterprise.list

# Update package list
apt update
```

## Cluster Configuration

### 1. Create a New Cluster (on first node)

```bash
# Create cluster (replace with your cluster name)
pvecm create my-proxmox-cluster

# Verify cluster status
pvecm status
```

### 2. Join Additional Nodes to Cluster

```bash
# On additional nodes, join the cluster
pvecm add <IP_OF_FIRST_NODE>

# Verify all nodes are members
pvecm nodes
```

### 3. Configure Corosync

Edit `/etc/corosync/corosync.conf`:

```conf
totem {
    version: 2
    cluster_name: my-proxmox-cluster
    config_version: 3
    ip_version: ipv4
    secauth: on
    transport: udpu
    interface {
        ringnumber: 0
        bindnetaddr: 10.0.0.0    # Your cluster network
        mcastport: 5405
        ttl: 1
    }
}

logging {
    to_logfile: yes
    logfile: /var/log/corosync/corosync.log
    to_syslog: yes
    timestamp: on
}

nodelist {
    node {
        name: node1
        nodeid: 1
        quorum_votes: 1
        ring0_addr: 10.0.0.101
    }
    node {
        name: node2
        nodeid: 2
        quorum_votes: 1
        ring0_addr: 10.0.0.102
    }
    node {
        name: node3
        nodeid: 3
        quorum_votes: 1
        ring0_addr: 10.0.0.103
    }
}

quorum {
    provider: corosync_votequorum
    expected_votes: 3
}
```

## API Setup and Authentication

### 1. Create API User for MCPman

```bash
# Create a dedicated user for MCPman
pveum user add mcpman@pve --password <SECURE_PASSWORD> --comment "MCPman API User"

# Create a role with necessary permissions
pveum role add MCPmanRole -privs "VM.Allocate,VM.Audit,VM.Clone,VM.Config.CDROM,VM.Config.CPU,VM.Config.Cloudinit,VM.Config.Disk,VM.Config.HWType,VM.Config.Memory,VM.Config.Network,VM.Config.Options,VM.Console,VM.Migrate,VM.Monitor,VM.PowerMgmt,Datastore.Allocate,Datastore.AllocateSpace,Datastore.Audit,Pool.Allocate,Pool.Audit,Sys.Audit,Sys.Console,Sys.Modify,Sys.PowerMgmt,SDN.Use"

# Assign role to user on root path
pveum aclmod / -user mcpman@pve -role MCPmanRole
```

### 2. Create API Token (Recommended)

```bash
# Create API token for mcpman user
pveum user token add mcpman@pve mcpman-token --privsep=0

# Note down the token ID and secret - you'll need these for MCPman configuration
```

### 3. Configure API Access

Edit `/etc/pve/datacenter.cfg`:

```conf
keyboard: en-us
migration: secure
console: html5
# Enable API token authentication
webauthn: 1
```

## Storage Configuration

### 1. Local Storage (Basic Setup)

```bash
# Create directory storage for ISOs and templates
pvesm add dir iso-storage --path /var/lib/vz/template --content iso,vztmpl

# Create directory storage for VM disks (if using local storage)
pvesm add dir vm-storage --path /var/lib/vz/images --content images
```

### 2. Shared Storage (Recommended for Production)

#### Option A: Ceph Storage

```bash
# Install Ceph on all nodes
pveceph install

# Create initial Ceph configuration
pveceph init --network 10.0.1.0/24

# Create monitors on all nodes
pveceph mon create

# Create OSDs (replace with your disk devices)
pveceph osd create /dev/sdb
pveceph osd create /dev/sdc

# Create pools for VM storage
pveceph pool create vm-pool --size 3 --min_size 2
pveceph pool create ct-pool --size 3 --min_size 2

# Add Ceph storage to Proxmox
pvesm add cephfs ceph-vm --pool vm-pool --content images
pvesm add cephfs ceph-ct --pool ct-pool --content images
```

#### Option B: NFS Storage

```bash
# Mount NFS share (configure NFS server separately)
pvesm add nfs nfs-storage --server 10.0.0.200 --export /proxmox/storage --content images,iso,vztmpl,backup
```

## Network Configuration

### 1. Configure Bridges

Edit `/etc/network/interfaces`:

```conf
# Management network
auto vmbr0
iface vmbr0 inet static
    address 10.0.0.101/24
    gateway 10.0.0.1
    bridge-ports ens18
    bridge-stp off
    bridge-fd 0

# VM network (with VLAN support)
auto vmbr1
iface vmbr1 inet manual
    bridge-ports ens19
    bridge-stp off
    bridge-fd 0
    bridge-vlan-aware yes
    bridge-vids 2-4094

# Cluster communication network
auto vmbr2
iface vmbr2 inet static
    address 10.0.1.101/24
    bridge-ports ens20
    bridge-stp off
    bridge-fd 0
```

### 2. Configure VLANs for Development Environments

```bash
# Create VLAN-aware bridge configuration
# VLANs 100-199: Development environments
# VLANs 200-299: Testing environments
# VLANs 300-399: Staging environments

# Example VLAN configuration in /etc/network/interfaces
auto vmbr1.100
iface vmbr1.100 inet static
    address 192.168.100.1/24
    vlan-raw-device vmbr1
```

## Templates and Images

### 1. Download Container Templates

```bash
# Download common LXC templates
pveam update
pveam download local debian-13-standard_13.0-1_amd64.tar.zst
pveam download local ubuntu-24.04-standard_24.04-1_amd64.tar.zst

# List available templates
pveam list local
```

### 2. Create VM Templates

```bash
# Download cloud-init enabled images  
cd /var/lib/vz/template/iso
wget https://cloud.debian.org/images/cloud/trixie/latest/debian-13-generic-amd64.qcow2

# Create a template VM
qm create 1000 --name debian-13-template --memory 2048 --cores 2 --net0 virtio,bridge=vmbr0
qm importdisk 1000 debian-13-generic-amd64.qcow2 local-lvm
qm set 1000 --scsihw virtio-scsi-pci --scsi0 local-lvm:vm-1000-disk-0
qm set 1000 --boot c --bootdisk scsi0
qm set 1000 --ide2 local-lvm:cloudinit
qm set 1000 --serial0 socket --vga serial0
qm set 1000 --agent enabled=1

# Convert to template
qm template 1000
```

### 3. Prepare Development Templates

Create specialized templates for each development environment type:

```bash
# Web development template (LAMP stack)
qm clone 1000 2000 --name web-dev-template
qm set 2000 --memory 4096 --cores 2
# Customize with LAMP stack installation scripts
qm template 2000

# Node.js development template
qm clone 1000 2001 --name nodejs-dev-template
qm set 2001 --memory 4096 --cores 2
# Customize with Node.js installation scripts
qm template 2001

# Python development template
qm clone 1000 2002 --name python-dev-template
qm set 2002 --memory 4096 --cores 2
# Customize with Python installation scripts
qm template 2002
```

## MCPman Integration

### 1. Environment Variables

Add to your `.env` file:

```env
# Proxmox Configuration
PROXMOX_MONITORING_ENABLED=true
PROXMOX_HEALTH_CHECK_INTERVAL=300
PROXMOX_MAX_CONCURRENT_PROVISIONS=3
PROXMOX_CLEANUP_FAILED_PROVISIONS=true

# Default Thresholds
PROXMOX_CPU_WARNING_THRESHOLD=80
PROXMOX_CPU_CRITICAL_THRESHOLD=95
PROXMOX_MEMORY_WARNING_THRESHOLD=85
PROXMOX_MEMORY_CRITICAL_THRESHOLD=95
PROXMOX_STORAGE_WARNING_THRESHOLD=85
PROXMOX_STORAGE_CRITICAL_THRESHOLD=95

# Cost Calculation
PROXMOX_CPU_RATE=0.03
PROXMOX_MEMORY_RATE=0.01
PROXMOX_STORAGE_RATE=0.0001
PROXMOX_CONTAINER_DISCOUNT=0.7

# Networking
PROXMOX_DEFAULT_BRIDGE=vmbr1
PROXMOX_VLAN_RANGE_START=100
PROXMOX_VLAN_RANGE_END=999
PROXMOX_DEV_IP_RANGE=192.168.100.0/24
PROXMOX_TEST_IP_RANGE=192.168.101.0/24
PROXMOX_STAGE_IP_RANGE=192.168.102.0/24

# Queue Configuration
PROXMOX_QUEUE_CONNECTION=database
PROXMOX_HEALTH_CHECK_QUEUE=proxmox-monitoring
PROXMOX_PROVISIONING_QUEUE=proxmox-provisioning
```

### 2. Database Setup

For production, MariaDB is recommended:

```bash
# Install MariaDB
sudo apt update
sudo apt install mariadb-server mariadb-client

# Secure MariaDB installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p
CREATE DATABASE mcpman_proxmox;
CREATE USER 'mcpman'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON mcpman_proxmox.* TO 'mcpman'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Update .env file with MariaDB configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mcpman_proxmox
DB_USERNAME=mcpman
DB_PASSWORD=secure_password

# Run migrations
php artisan migrate

# Seed initial data (optional)
php artisan db:seed --class=ProxmoxTemplateSeeder
```

### 3. Queue Workers

Set up queue workers for background processing:

```bash
# Create systemd service for queue workers
sudo tee /etc/systemd/system/mcpman-proxmox-worker.service > /dev/null <<EOF
[Unit]
Description=MCPman Proxmox Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path/to/mcpman/artisan queue:work --queue=proxmox-monitoring,proxmox-provisioning,proxmox --sleep=3 --tries=3 --max-time=3600
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

# Enable and start the service
sudo systemctl enable mcpman-proxmox-worker
sudo systemctl start mcpman-proxmox-worker
```

### 4. Scheduled Tasks

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Health checks every 5 minutes
    $schedule->job(new \App\Jobs\MonitorMcpHealth())
             ->everyFiveMinutes()
             ->onQueue('proxmox-monitoring');

    // Resource usage updates every 15 minutes
    $schedule->command('proxmox:update-resources')
             ->everyFifteenMinutes();

    // Cleanup expired environments daily
    $schedule->command('proxmox:cleanup-expired')
             ->daily();

    // Cost calculation updates hourly
    $schedule->command('proxmox:update-costs')
             ->hourly();
}
```

## Security Hardening

### 1. Firewall Configuration

```bash
# Configure iptables/pve-firewall
# Create firewall rules for API access
cat > /etc/pve/firewall/cluster.fw <<EOF
[OPTIONS]
enable: 1

[RULES]
# Allow MCPman server access to Proxmox API
IN ACCEPT -source 10.0.0.0/24 -dport 8006 -proto tcp
# Allow cluster communication
IN ACCEPT -source 10.0.1.0/24 -dport 5404:5412 -proto udp
# Allow migration traffic
IN ACCEPT -source 10.0.1.0/24 -dport 60000:60050 -proto tcp
EOF
```

### 2. SSL/TLS Configuration

```bash
# Generate proper SSL certificates (replace with your domain)
openssl req -new -x509 -nodes -newkey rsa:4096 -keyout /etc/pve/local/pve-ssl.key -out /etc/pve/local/pve-ssl.pem -days 3650 -subj "/C=US/ST=State/L=City/O=Organization/CN=proxmox.yourdomain.com"

# Restart pveproxy to load new certificates
systemctl restart pveproxy
```

### 3. API Security

```bash
# Limit API access by IP
echo "PVE.Utils.API2Request = (function(originalFunction) {
    return function(config) {
        // Add API security headers
        if (!config.headers) config.headers = {};
        config.headers['X-Requested-With'] = 'XMLHttpRequest';
        return originalFunction.call(this, config);
    };
})(PVE.Utils.API2Request);" > /usr/share/pve-manager/js/mcpman-api-security.js
```

## Monitoring and Maintenance

### 1. Health Monitoring Script

```bash
#!/bin/bash
# /usr/local/bin/proxmox-health-check.sh

# Check cluster status
CLUSTER_STATUS=$(pvecm status | grep "Cluster information" -A 10)
echo "Cluster Status: $CLUSTER_STATUS"

# Check node health
for node in $(pvecm nodes | grep -v "Node" | awk '{print $3}'); do
    echo "Checking node: $node"
    ssh $node "uptime; df -h; free -h"
done

# Check Ceph status (if using Ceph)
if command -v ceph &> /dev/null; then
    ceph status
fi
```

### 2. Backup Script

```bash
#!/bin/bash
# /usr/local/bin/proxmox-backup.sh

# Backup Proxmox configuration
vzdump --all --compress lzo --storage backup-storage

# Backup cluster configuration
tar -czf /backup/pve-cluster-config-$(date +%Y%m%d).tar.gz /etc/pve/

# Cleanup old backups (keep 30 days)
find /backup/ -name "*.tar.gz" -mtime +30 -delete
```

### 3. Log Monitoring

```bash
# Monitor important logs
tail -f /var/log/pveproxy/access.log
tail -f /var/log/pvedaemon.log
tail -f /var/log/corosync/corosync.log
```

## Troubleshooting

### Common Issues and Solutions

#### 1. Cluster Communication Issues

```bash
# Check corosync status
systemctl status corosync
journalctl -u corosync -f

# Check network connectivity
ping <other-node-ip>
telnet <other-node-ip> 5405

# Restart cluster services
systemctl restart corosync
systemctl restart pve-cluster
```

#### 2. API Authentication Problems

```bash
# Test API access
curl -k -d "username=mcpman@pve&password=<password>" https://<proxmox-ip>:8006/api2/json/access/ticket

# Check user permissions
pveum user list
pveum aclmod / -user mcpman@pve -role MCPmanRole
```

#### 3. Storage Issues

```bash
# Check storage configuration
pvesm status
pvesm list <storage-name>

# Check disk space
df -h
pvesm path <storage-name>
```

#### 4. VM/Container Creation Failures

```bash
# Check available resources
pvesh get /nodes/<node>/status
pvesh get /nodes/<node>/storage

# Check templates
pveam list local
qm list
```

### Performance Optimization

#### 1. VM Performance

```bash
# Optimize VM settings for development workloads
qm set <vmid> --cpu host --numa 1
qm set <vmid> --balloon 0  # Disable memory ballooning for development VMs
qm set <vmid> --scsi0 <storage>:vm-<vmid>-disk-0,cache=writeback,iothread=1
```

#### 2. Container Performance

```bash
# Optimize container settings
pct set <ctid> --cores 2 --cpulimit 2
pct set <ctid> --memory 4096 --swap 0
```

#### 3. Network Performance

```bash
# Enable multiqueue for high-performance networking
qm set <vmid> --net0 virtio,bridge=vmbr0,queues=4
```

## Support and Resources

- **Proxmox VE Documentation**: https://pve.proxmox.com/pve-docs/
- **Proxmox VE API Documentation**: https://pve.proxmox.com/pve-docs/api-viewer/
- **Proxmox Community Forum**: https://forum.proxmox.com/
- **MCPman Documentation**: See `docs/` directory in this repository

## Next Steps

1. **Test Environment**: Create a test development environment to validate the setup
2. **Monitoring**: Set up comprehensive monitoring and alerting
3. **Backup Strategy**: Implement automated backup procedures
4. **Documentation**: Document your specific configuration and procedures
5. **Training**: Train your team on the new development environment provisioning system

This completes the Proxmox platform setup guide. Follow these instructions carefully and adapt them to your specific environment and requirements.