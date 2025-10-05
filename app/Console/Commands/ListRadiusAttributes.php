<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Package;

class ListRadiusAttributes extends Command
{
    protected $signature = 'radius:list-attributes';
    protected $description = 'List all RADIUS attributes used in package creation';

    public function handle()
    {
        $this->info("=== RADIUS ATTRIBUTES USED IN PACKAGE CREATION ===\n");
        
        $this->info("📋 RadGroupCheck (Restrictions/Checks)");
        $this->info("Table: radgroupcheck");
        $this->line("┌─────────────────────────┬─────┬─────────────────────────────────────────┐");
        $this->line("│ Attribute               │ Op  │ Description                             │");
        $this->line("├─────────────────────────┼─────┼─────────────────────────────────────────┤");
        $this->line("│ Simultaneous-Use        │ :=  │ Max concurrent sessions per user        │");
        $this->line("│ Session-Timeout         │ :=  │ Per-session timeout in seconds          │");
        $this->line("│ Service-Type            │ :=  │ Always 'Login-User' for internet access │");
        $this->line("│ Mikrotik-Total-Limit    │ :=  │ Data limit in bytes (if data_limit set) │");
        $this->line("└─────────────────────────┴─────┴─────────────────────────────────────────┘");
        
        $this->info("\n📤 RadGroupReply (Response/Configuration)");
        $this->info("Table: radgroupreply");
        $this->line("┌─────────────────────────┬─────┬─────────────────────────────────────────┐");
        $this->line("│ Attribute               │ Op  │ Description                             │");
        $this->line("├─────────────────────────┼─────┼─────────────────────────────────────────┤");
        $this->line("│ Mikrotik-Total-Limit    │ :=  │ Data limit in bytes (if data_limit set) │");
        $this->line("│ WISPr-Bandwidth-Max-Up  │ :=  │ Upload speed in bits per second         │");
        $this->line("│ WISPr-Bandwidth-Max-Down│ :=  │ Download speed in bits per second       │");
        $this->line("│ Mikrotik-Rate-Limit     │ :=  │ MikroTik format: 'uploadK/downloadK'    │");
        $this->line("│ Idle-Timeout            │ :=  │ Idle timeout: 300 seconds (5 minutes)  │");
        $this->line("│ Mikrotik-Address-List   │ :=  │ 'trial_users' or 'paid_users'           │");
        $this->line("│ Reply-Message           │ :=  │ Welcome message with package name       │");

        $this->line("└─────────────────────────┴─────┴─────────────────────────────────────────┘");
        
        $this->info("\n👤 RadCheck (Individual User Attributes)");
        $this->info("Table: radcheck");
        $this->line("┌─────────────────────────┬─────┬─────────────────────────────────────────┐");
        $this->line("│ Attribute               │ Op  │ Description                             │");
        $this->line("├─────────────────────────┼─────┼─────────────────────────────────────────┤");
        $this->line("│ Cleartext-Password      │ :=  │ User password for authentication        │");
        $this->line("└─────────────────────────┴─────┴─────────────────────────────────────────┘");
        
        $this->info("\n🔗 RadUserGroup (User-Group Assignment)");
        $this->info("Table: radusergroup");
        $this->line("┌─────────────────────────┬─────────────────────────────────────────────┐");
        $this->line("│ Field                   │ Description                                 │");
        $this->line("├─────────────────────────┼─────────────────────────────────────────────┤");
        $this->line("│ username                │ Username of the subscriber                  │");
        $this->line("│ groupname               │ Package group name (package_X)             │");
        $this->line("│ priority                │ Group priority (default: 1)                │");
        $this->line("└─────────────────────────┴─────────────────────────────────────────────┘");
        
        $this->info("\n⚙️  ATTRIBUTE VALUE CALCULATIONS");
        $this->info("─────────────────────────────────────────");
        $this->line("• Session-Timeout:");
        $this->line("  - Hourly: duration_value × 3600 seconds");
        $this->line("  - Daily: duration_value × 86400 seconds");
        $this->line("  - Weekly: duration_value × 604800 seconds");
        $this->line("  - Monthly: duration_value × 2592000 seconds");
        $this->line("  - Yearly: duration_value × 31536000 seconds");
        
        $this->line("\n• Bandwidth Calculations:");
        $this->line("  - WISPr attributes: bandwidth_kbps × 1000 (to get bps)");
        $this->line("  - Mikrotik-Rate-Limit: 'uploadK/downloadK' format");
        $this->line("  - Default: 2000K/5000K if not specified");
        
        $this->line("\n• Data Limit:");
        $this->line("  - Mikrotik-Total-Limit: data_limit_mb × 1024 × 1024");
        $this->line("  - Only added if package has data_limit set");
        
        $this->line("\n• Address List:");
        $this->line("  - trial_users: for packages with is_trial = true");
        $this->line("  - paid_users: for packages with is_trial = false");
        
        $this->info("\n✅ IMPLEMENTATION STATUS");
        $this->info("─────────────────────────");
        $this->line("✓ All attributes from your SQL example implemented");
        $this->line("✓ Group-level session management (Session-Timeout)");
        $this->line("✓ Individual user authentication (Cleartext-Password)");
        $this->line("✓ Bandwidth control (WISPr + MikroTik formats)");
        $this->line("✓ Data limit enforcement (Mikrotik-Total-Limit)");
        $this->line("✓ Session timeout and idle timeout");
        $this->line("✓ Traffic management (Mikrotik-Address-List)");
        $this->line("✓ User-friendly welcome messages");
        $this->line("✓ MikroTik integration (Mikrotik-Rate-Limit, Mikrotik-Address-List)");
        
        $this->info("\n🔄 PACKAGE CREATION FLOW");
        $this->info("─────────────────────────");
        $this->line("1. Package::setupRadiusGroup() creates group attributes");
        $this->line("2. RadGroupCheck gets restrictions (timeouts, limits)");
        $this->line("3. RadGroupReply gets configuration (bandwidth, messages)");
        $this->line("4. Subscription::createRadiusUser() adds individual user");
        $this->line("5. RadCheck gets user password");
        $this->line("6. RadUserGroup assigns user to package group");
        $this->line("7. MikroTik enforces all attributes for user sessions");
    }
}