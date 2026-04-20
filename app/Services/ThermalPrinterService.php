<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Throwable;

class ThermalPrinterService
{
    private const LINE_WIDTH = 42;

    public function available(): bool
    {
        return $this->bootEscpos();
    }

    public function enabled(): bool
    {
        return filter_var(setting_value('thermal_printer_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public function configured(): bool
    {
        $config = $this->configuration();

        return match ($config['connector']) {
            'network' => $config['host'] !== '' && $config['port'] > 0,
            'file', 'windows' => $config['target'] !== '',
            default => false,
        };
    }

    public function summary(): string
    {
        $config = $this->configuration();

        return match ($config['connector']) {
            'network' => $config['host'] !== ''
                ? strtoupper($config['connector']) . ': ' . $config['host'] . ':' . $config['port']
                : 'NETWORK: host required',
            default => strtoupper($config['connector']) . ': ' . ($config['target'] !== '' ? $config['target'] : 'target required'),
        };
    }

    public function printSaleReceipt(array $sale): void
    {
        $printer = null;

        try {
            $printer = $this->createPrinter();
            $this->printSale($printer, $sale);
        } catch (Throwable $exception) {
            throw new RuntimeException('Thermal printing failed: ' . $exception->getMessage(), 0, $exception);
        } finally {
            if ($printer !== null) {
                try {
                    $printer->close();
                } catch (Throwable) {
                }
            }
        }
    }

    public function printTestPage(array $context = []): void
    {
        $printer = null;
        $brandName = (string) setting_value('business_name', config('app.name', 'NovaPOS'));

        try {
            $printer = $this->createPrinter();
            $printer->setJustification($this->printerClass()::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text($this->safeText($brandName) . "\n");
            $printer->setEmphasis(false);
            $printer->text("THERMAL PRINTER TEST\n");
            $printer->text($this->separator());
            $printer->setJustification($this->printerClass()::JUSTIFY_LEFT);
            $printer->text($this->line('Connector', $this->configuration()['connector']));
            $printer->text($this->line('Target', $this->summary()));
            $printer->text($this->line('Generated', date('Y-m-d H:i')));

            if (($context['requested_by'] ?? '') !== '') {
                $printer->text($this->line('Requested By', (string) $context['requested_by']));
            }

            if (($context['branch'] ?? '') !== '') {
                $printer->text($this->line('Branch', (string) $context['branch']));
            }

            $printer->text($this->separator());
            $printer->text("If this printed cleanly, ESC/POS receipt output is ready.\n");
            $printer->feed(2);
            $printer->cut();
        } catch (Throwable $exception) {
            throw new RuntimeException('Thermal printer test failed: ' . $exception->getMessage(), 0, $exception);
        } finally {
            if ($printer !== null) {
                try {
                    $printer->close();
                } catch (Throwable) {
                }
            }
        }
    }

    private function createPrinter(): object
    {
        if (!$this->enabled()) {
            throw new RuntimeException('Direct thermal printing is disabled in Settings.');
        }

        if (!$this->available()) {
            throw new RuntimeException('ESC/POS printer library is unavailable. Keep the local vendor package or install the dependency.');
        }

        if (!$this->configured()) {
            throw new RuntimeException('Thermal printer settings are incomplete. Configure the connector and printer target first.');
        }

        $printerClass = $this->printerClass();

        return new $printerClass($this->connector());
    }

    private function printSale(object $printer, array $sale): void
    {
        $printerClass = $this->printerClass();
        $brandName = (string) setting_value('business_name', config('app.name', 'NovaPOS'));
        $businessAddress = (string) setting_value('business_address', '');
        $businessPhone = (string) setting_value('business_phone', '');
        $receiptHeader = (string) setting_value('receipt_header', '');
        $receiptFooter = (string) setting_value('receipt_footer', '');
        $loyaltyDiscount = (float) ($sale['loyalty_discount_total'] ?? 0);
        $redeemedPoints = (int) ($sale['loyalty_points_redeemed'] ?? 0);
        $earnedPoints = (int) ($sale['loyalty_points_earned'] ?? 0);
        $creditAmount = (float) ($sale['credit_amount'] ?? 0);
        $cashTendered = (float) ($sale['cash_tendered'] ?? 0);
        $collectedAmount = (float) ($sale['collected_amount'] ?? $sale['amount_paid'] ?? 0);

        $printer->setJustification($printerClass::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text($this->safeText($brandName) . "\n");
        $printer->setEmphasis(false);

        foreach ([$businessAddress, $businessPhone, (string) ($sale['branch_name'] ?? ''), (string) ($sale['sale_number'] ?? ''), (string) ($sale['completed_at'] ?? $sale['created_at'] ?? '')] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $printer->text($this->safeText($line) . "\n");
            }
        }

        if ($receiptHeader !== '') {
            $printer->text($this->safeText($receiptHeader) . "\n");
        }

        $printer->text($this->separator());
        $printer->setJustification($printerClass::JUSTIFY_LEFT);
        $printer->text($this->line('Cashier', (string) ($sale['cashier_name'] ?? '')));
        $printer->text($this->line('Customer', (string) ($sale['customer_name'] ?? 'Walk-in Customer')));

        if ($creditAmount > 0.0001) {
            $printer->text($this->line('Open Account', $this->money($creditAmount)));
        }

        if ($redeemedPoints > 0 || $earnedPoints > 0) {
            $loyaltyText = ($redeemedPoints > 0 ? '-' . $redeemedPoints . ' pts' : '0 pts')
                . ($earnedPoints > 0 ? ' / +' . $earnedPoints . ' pts' : '');
            $printer->text($this->line('Loyalty', $loyaltyText));
        }

        $printer->text($this->separator());

        foreach (($sale['items'] ?? []) as $item) {
            $printer->text($this->safeText((string) ($item['product_name'] ?? 'Item')) . "\n");
            $printer->text($this->line(
                (string) ($item['quantity'] ?? 0) . ' x ' . $this->money((float) ($item['unit_price'] ?? 0)),
                $this->money((float) ($item['line_total'] ?? 0))
            ));
        }

        $printer->text($this->separator());
        $printer->text($this->line('Subtotal', $this->money((float) ($sale['subtotal'] ?? 0))));
        $printer->text($this->line('Item Discount', $this->money((float) ($sale['item_discount_total'] ?? 0))));
        $printer->text($this->line('Order Discount', $this->money((float) ($sale['order_discount_total'] ?? 0))));

        if ($loyaltyDiscount > 0.0001) {
            $printer->text($this->line('Loyalty Discount', $this->money($loyaltyDiscount)));
        }

        $printer->text($this->line('Tax', $this->money((float) ($sale['tax_total'] ?? 0))));
        $printer->setEmphasis(true);
        $printer->text($this->line('TOTAL', $this->money((float) ($sale['grand_total'] ?? 0))));
        $printer->setEmphasis(false);
        $printer->text($this->line('Paid Now', $this->money($collectedAmount)));
        if ($cashTendered > 0.0001) {
            $printer->text($this->line('Cash Given', $this->money($cashTendered)));
        }

        if ($creditAmount > 0.0001) {
            $printer->text($this->line('Assigned to Credit', $this->money($creditAmount)));
        }

        $printer->text($this->line('Change', $this->money((float) ($sale['change_due'] ?? 0))));

        foreach (($sale['payments'] ?? []) as $payment) {
            $printer->text($this->line(
                $this->paymentLabel((string) ($payment['payment_method'] ?? 'payment')),
                $this->money((float) ($payment['amount'] ?? 0))
            ));

            foreach (pos_payment_detail_lines($payment) as $detailLine) {
                $printer->text('  ' . $this->safeText($detailLine) . "\n");
            }
        }

        if ($redeemedPoints > 0) {
            $printer->text($this->line('Points Redeemed', $redeemedPoints . ' pts'));
        }

        if ($earnedPoints > 0) {
            $printer->text($this->line('Points Earned', $earnedPoints . ' pts'));
        }

        if (!empty($sale['notes'])) {
            $printer->text($this->separator());
            $printer->text("Notes\n");
            $printer->text($this->safeText((string) $sale['notes']) . "\n");
        }

        if ($receiptFooter !== '') {
            $printer->text($this->separator());
            $printer->setJustification($printerClass::JUSTIFY_CENTER);
            $printer->text($this->safeText($receiptFooter) . "\n");
        }

        $printer->feed(2);
        $printer->cut();
    }

    private function connector(): object
    {
        $config = $this->configuration();

        return match ($config['connector']) {
            'network' => $this->networkConnector($config['host'], $config['port']),
            'file' => $this->fileConnector($config['target']),
            default => $this->windowsConnector($config['target']),
        };
    }

    private function windowsConnector(string $target): object
    {
        $class = $this->connectorClass('WindowsPrintConnector');

        return new $class($target);
    }

    private function networkConnector(string $host, int $port): object
    {
        $class = $this->connectorClass('NetworkPrintConnector');

        return new $class($host, $port);
    }

    private function fileConnector(string $target): object
    {
        $class = $this->connectorClass('FilePrintConnector');

        return new $class($target);
    }

    private function printerClass(): string
    {
        return 'Mike42\\Escpos\\Printer';
    }

    private function connectorClass(string $connector): string
    {
        return 'Mike42\\Escpos\\PrintConnectors\\' . $connector;
    }

    private function configuration(): array
    {
        $connector = trim((string) setting_value('thermal_printer_connector', 'windows'));
        $host = trim((string) setting_value('thermal_printer_host', ''));
        $target = trim((string) setting_value('thermal_printer_target', ''));
        $port = (int) setting_value('thermal_printer_port', '9100');

        return [
            'connector' => in_array($connector, ['windows', 'network', 'file'], true) ? $connector : 'windows',
            'host' => $host,
            'target' => $target,
            'port' => $port > 0 ? $port : 9100,
        ];
    }

    private function bootEscpos(): bool
    {
        $printerClass = $this->printerClass();

        if (class_exists($printerClass)) {
            return true;
        }

        $autoloaders = [
            base_path('vendor/autoload.php'),
            base_path('www.optionone.com/extensions/vendor/autoload.php'),
        ];

        foreach ($autoloaders as $autoload) {
            if (is_file($autoload)) {
                require_once $autoload;

                if (class_exists($printerClass)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function separator(): string
    {
        return str_repeat('-', self::LINE_WIDTH) . "\n";
    }

    private function line(string $left, string $right): string
    {
        $left = $this->clip($this->safeText($left), self::LINE_WIDTH - 8);
        $right = $this->clip($this->safeText($right), 18);
        $space = max(1, self::LINE_WIDTH - strlen($left) - strlen($right));

        return $left . str_repeat(' ', $space) . $right . "\n";
    }

    private function clip(string $value, int $limit): string
    {
        if ($limit <= 0 || strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(substr($value, 0, max(1, $limit - 3))) . '...';
    }

    private function safeText(string $value): string
    {
        $value = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));

        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

            if (is_string($ascii) && $ascii !== '') {
                return str_replace(["\r\n", "\r"], "\n", $ascii);
            }
        }

        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    private function money(float $amount): string
    {
        return currency_symbol() . ' ' . number_format($amount, 2);
    }

    private function paymentLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Cash',
            'card' => 'Card',
            'mobile_money' => 'Mobile Money',
            'cheque' => 'Cheque',
            'split' => 'Split Payment',
            'credit' => 'Credit',
            default => ucwords(str_replace('_', ' ', $method)),
        };
    }
}
