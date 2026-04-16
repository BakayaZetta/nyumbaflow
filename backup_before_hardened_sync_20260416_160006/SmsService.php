<?php

class SmsService {
    private $apiKey;
    private $partnerId;
    private $shortCode;
    private $apiUrl = "https://mysms.celcomafrica.com/api/services/sendsms/";

    public function __construct($apiKey = null, $partnerId = null, $shortCode = 'HOMESYNC') {
        // In a real app, these would come from a config or database
        $this->apiKey = $apiKey;
        $this->partnerId = $partnerId;
        $this->shortCode = $shortCode;
    }

    /**
     * Send a direct message to a specific tenant
     */
    public function sendDirectMessage($phone, $message, $propertyName = null) {
        $shortCode = $propertyName ? strtoupper(substr($propertyName, 0, 10)) : $this->shortCode;
        $this->shortCode = $shortCode;
        return $this->sendSms($phone, $message);
    }

    /**
     * Send a general SMS
     */
    public function sendSms($phone, $message) {
        if (empty($this->apiKey) || empty($this->partnerId)) {
            error_log("SMS not sent: API credentials missing.");
            return false;
        }

        // Format phone number to 254...
        $phone = $this->formatPhoneNumber($phone);

        $payload = [
            'apikey' => $this->apiKey,
            'partnerID' => $this->partnerId,
            'message' => $message,
            'shortcode' => $this->shortCode,
            'mobile' => $phone
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log("SMS API Error: " . $err);
            return false;
        }

        return json_decode($response, true);
    }

    /**
     * Specialized: Send Rent Invoice
     */
    public function sendInvoice($phone, $name, $amount, $dueDate, $propertyName) {
        $msg = "Hello $name, your rent for $propertyName is KES " . number_format($amount) . ". Due date: $dueDate. Please pay via M-Pesa or Bank. Thank you.";
        $this->shortCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $propertyName), 0, 11));
        return $this->sendSms($phone, $msg);
    }

    /**
     * Specialized: Send Monthly Bill Breakdown
     */
    public function sendMonthlyBreakdown($phone, $name, $data) {
        // Data contains: rent, water_units, water_cost, wifi, garbage, credit, total, property
        $msg = "Hello $name, Bill for {$data['property']} ({$data['month']}):\n";
        $msg .= "Rent: " . number_format($data['rent']) . "\n";
        if ($data['water_units'] > 0) $msg .= "Water ({$data['water_units']} units): " . number_format($data['water_cost']) . "\n";
        if ($data['wifi'] > 0) $msg .= "WiFi: " . number_format($data['wifi']) . "\n";
        if ($data['garbage'] > 0) $msg .= "Garbage: " . number_format($data['garbage']) . "\n";
        if ($data['credit'] > 0) $msg .= "Prev Credit: -" . number_format($data['credit']) . "\n";
        $msg .= "TOTAL DUE: KES " . number_format($data['total']) . ". Pay by 5th. Thank you.";
        
        $this->shortCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $data['property']), 0, 11));
        return $this->sendSms($phone, $msg);
    }

    /**
     * Specialized: Send Payment Confirmation
     */
    public function sendPaymentConfirmation($phone, $name, $amount, $balance, $propertyName) {
        $msg = "Hello $name, we have received KES " . number_format($amount) . " for your house at $propertyName. Your current balance is KES " . number_format($balance) . ". Thank you.";
        $this->shortCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $propertyName), 0, 11));
        return $this->sendSms($phone, $msg);
    }

    /**
     * Specialized: Send Bulk Notice
     */
    /**
     * Send monthly bill summaries to all tenants for a property
     */
    public function sendMonthlyBills($pdo, $property_id, $month, $year) {
        // Fetch property name
        $stmt = $pdo->prepare("SELECT name FROM properties WHERE id = ?");
        $stmt->execute([$property_id]);
        $propertyName = $stmt->fetchColumn();

        // Fetch all active tenants for this property and their summed bills
        $stmt = $pdo->prepare("
            SELECT t.id, t.name, t.phone_number, SUM(b.balance) as total_due
            FROM tenants t
            JOIN bills b ON t.id = b.tenant_id
            WHERE t.property_id = ? AND t.status = 'active' 
            AND b.month = ? AND b.year = ?
            GROUP BY t.id
        ");
        $stmt->execute([$property_id, $month, $year]);
        $tenants = $stmt->fetchAll();

        foreach ($tenants as $tenant) {
            // Fetch breakdown for the SMS
            $bStmt = $pdo->prepare("SELECT bill_type, amount FROM bills WHERE tenant_id = ? AND month = ? AND year = ?");
            $bStmt->execute([$tenant['id'], $month, $year]);
            $bills = $bStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $msg = "Hello {$tenant['name']}, your total bill for $propertyName ($month) is KES " . number_format($tenant['total_due']) . ". ";
            if (isset($bills['rent'])) $msg .= "Rent: " . number_format($bills['rent']) . ", ";
            if (isset($bills['water'])) $msg .= "Water: " . number_format($bills['water']) . ", ";
            if (isset($bills['wifi'])) $msg .= "WiFi: " . number_format($bills['wifi']) . ", ";
            if (isset($bills['garbage'])) $msg .= "Garbage: " . number_format($bills['garbage']) . ". ";
            $msg .= "Please pay by the 5th. Thank you.";

            $this->shortCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $propertyName), 0, 11));
            $this->sendSms($tenant['phone_number'], $msg);
        }
        return true;
    }

    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
            $phone = '254' . $phone;
        }
        return $phone;
    }
}
?>
