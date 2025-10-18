# USSD WiFi Portal - Complete Step-by-Step Flow

## Every Single USSD Screen & User Interaction

```mermaid
flowchart TD
    START[📱 User Dials *123#] --> MAIN{🏠 Main Menu<br/>1. Buy Package<br/>2. Free Trial<br/>3. My Token<br/>4. Help}
    
    %% Buy Package Flow
    MAIN -->|1| PACKAGES{📦 Select Package<br/>1. Basic GH₵1<br/>2. Standard GH₵3<br/>3. Premium GH₵5<br/>4. Pro GH₵10}
    
    PACKAGES --> CONFIRM{✅ Confirm<br/>Standard Package<br/>GH₵3.00<br/>500MB, 6hrs<br/><br/>1. Confirm & Pay<br/>2. Cancel}
    
    CONFIRM -->|1| REGISTER{👤 Check Registration}
    REGISTER -->|Registered| PAY[💳 Process Payment<br/>Mobile Money]
    REGISTER -->|Not Registered| QUICK_REG[📝 Quick Register<br/>Enter Your Name]
    
    QUICK_REG --> PAY
    
    PAY --> PAYMENT_RESULT{💰 Payment Status}
    PAYMENT_RESULT -->|Success| TOKEN_GEN[🎉 Generate Token<br/>WIFI-A7B9-C2D5]
    PAYMENT_RESULT -->|Failed| PAYMENT_FAIL[❌ Payment Failed<br/>1. Retry<br/>2. Cancel]
    
    TOKEN_GEN --> SUCCESS[📨 Send Token<br/>USSD + SMS<br/>🏁 Complete]
    PAYMENT_FAIL --> CONFIRM
    
    %% Free Trial Flow
    MAIN -->|2| TRIAL_CHECK{🔍 Check Trial<br/>Eligibility}
    TRIAL_CHECK -->|Available| TRIAL_GEN[🎁 Generate Trial<br/>TRIAL-X1Y2-Z3A4<br/>30 minutes]
    TRIAL_CHECK -->|Used Before| TRIAL_DENY[❌ Trial Already Used<br/>1. Buy Package<br/>2. Exit]
    
    TRIAL_GEN --> TRIAL_SUCCESS[📨 Send Trial Token<br/>🏁 Complete]
    TRIAL_DENY --> PACKAGES
    
    %% My Token Flow
    MAIN -->|3| TOKEN_CHECK{🔍 Check Active<br/>Token}
    TOKEN_CHECK -->|Has Token| SHOW_TOKEN[📱 Active Token<br/>WIFI-A7B9-C2D5<br/>4h 23m left<br/>120MB/500MB used<br/><br/>1. Extend<br/>2. Buy New]
    TOKEN_CHECK -->|No Token| NO_TOKEN[❌ No Active Token<br/>1. Buy Package<br/>2. Free Trial]
    
    SHOW_TOKEN -->|1| PACKAGES
    NO_TOKEN --> PACKAGES
    
    %% Help Flow
    MAIN -->|4| HELP[� Support Info<br/>WiFi: wifi.portal.com<br/>WhatsApp: +233501234567<br/>Email: support@portal.com<br/>🏁 Complete]
    
    %% Styling
    classDef success fill:#d4edda,stroke:#28a745,stroke-width:2px
    classDef error fill:#f8d7da,stroke:#dc3545,stroke-width:2px
    classDef process fill:#cce5ff,stroke:#0066cc,stroke-width:2px
    classDef decision fill:#fff3cd,stroke:#856404,stroke-width:2px
    
    class SUCCESS,TRIAL_SUCCESS,HELP success
    class PAYMENT_FAIL,TRIAL_DENY,NO_TOKEN error
    class PAY,TOKEN_GEN,TRIAL_GEN process
    class MAIN,PACKAGES,CONFIRM,REGISTER,TRIAL_CHECK,TOKEN_CHECK,PAYMENT_RESULT decision
```

## Simplified User Paths

### � **Path 1: Buy Package (New User)**
```
*123# → Main Menu → Buy Package → Select Standard → Confirm 
→ Register Name → Pay → Token Generated → SMS Sent ✅
```

### 🛒 **Path 2: Buy Package (Returning User)**
```
*123# → Main Menu → Buy Package → Select Premium → Confirm 
→ Pay → Token Generated → SMS Sent ✅
```

### 🎁 **Path 3: Get Free Trial**
```
*123# → Main Menu → Free Trial → Check Eligibility 
→ Generate Trial → SMS Sent ✅
```

### 📱 **Path 4: Check My Token**
```
*123# → Main Menu → My Token → Show Active Token 
→ 4h 23m left, 120MB used ✅
```

### 🆘 **Path 5: Get Help**
```
*123# → Main Menu → Help → Contact Information ✅
```

## Sample USSD Screens

### 🏠 **Main Menu**
```
🌐 WiFi Portal
1. Buy Internet Package
2. Get Free Trial (30min)
3. My Active Token
4. Help & Support
```

### 📦 **Package Selection**
```
Select Package:
1. Basic - GH₵1
   100MB • 1 hour
2. Standard - GH₵3 ⭐
   500MB • 6 hours  
3. Premium - GH₵5
   1GB • 12 hours
4. Pro - GH₵10
   3GB • 24 hours
```

### ✅ **Confirmation**
```
Standard Package
Price: GH₵3.00
Data: 500MB
Valid: 6 hours

1. Confirm & Pay
2. Cancel
```

### 🎉 **Success**
```
Payment Successful!

Token: WIFI-A7B9-C2D5
Valid: 6 hours
Expires: Oct 17, 8:00PM

SMS sent with login details.
```

### 🎁 **Trial Success**
```
Free Trial Active!

Token: TRIAL-X1Y2-Z3A4
Valid: 30 minutes
Expires: Oct 17, 3:00PM

Connect at wifi.portal.com
```

