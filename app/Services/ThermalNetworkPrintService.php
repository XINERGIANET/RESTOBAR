<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ThermalNetworkPrintService
{
    public function sendRaw(string $host, int $port, string $payload, int $timeoutSeconds = 4): void
    {
        $host = trim($host);
        if ($host === '' || $port <= 0) {
            throw new RuntimeException('Host o puerto de impresora no válido.');
        }

        $errno = 0;
        $errstr = '';
        $target = sprintf('tcp://%s:%d', $host, $port);
        $fp = @stream_socket_client($target, $errno, $errstr, $timeoutSeconds, STREAM_CLIENT_CONNECT);
        if ($fp === false) {
            throw new RuntimeException("No se pudo conectar a la ticketera ({$host}:{$port}): {$errstr} ({$errno})");
        }

        try {
            stream_set_timeout($fp, $timeoutSeconds);
            $written = @fwrite($fp, $payload);
            if ($written === false || $written < strlen($payload)) {
                throw new RuntimeException('Envío incompleto a la ticketera.');
            }
            fflush($fp);
        } finally {
            fclose($fp);
        }
    }

    public function sendRawToWindowsPrinter(string $printerName, string $payload, int $timeoutSeconds = 8): void
    {
        $printerName = trim($printerName);
        if ($printerName === '') {
            throw new RuntimeException('Nombre de impresora no válido.');
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException('La impresión USB local está disponible solo en Windows.');
        }

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir) && ! @mkdir($tmpDir, 0775, true) && ! is_dir($tmpDir)) {
            throw new RuntimeException('No se pudo crear el directorio temporal para impresión.');
        }

        $payloadPath = tempnam($tmpDir, 'ticket_payload_');
        $scriptPath = tempnam($tmpDir, 'ticket_print_');
        if ($payloadPath === false || $scriptPath === false) {
            throw new RuntimeException('No se pudo crear archivos temporales para impresión.');
        }

        $scriptPathPs1 = $scriptPath.'.ps1';
        @rename($scriptPath, $scriptPathPs1);

        $psScript = <<<'PS1'
param(
    [Parameter(Mandatory=$true)][string]$PrinterName,
    [Parameter(Mandatory=$true)][string]$PayloadPath,
    [Parameter(Mandatory=$true)][string]$TempDir
)

if (!(Test-Path -LiteralPath $PayloadPath)) {
    throw "No se encontró el archivo de payload."
}

if (!(Test-Path -LiteralPath $TempDir)) {
    New-Item -ItemType Directory -Path $TempDir -Force | Out-Null
}

$env:TEMP = $TempDir
$env:TMP = $TempDir

Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;

public static class RawPrinterHelper {
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    public class DOCINFOA {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
    }

    [DllImport("winspool.Drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi)]
    public static extern bool OpenPrinter(string szPrinter, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In] DOCINFOA di);

    [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    public static bool SendBytesToPrinter(string printerName, byte[] bytes, out string errorMessage) {
        errorMessage = "";
        IntPtr hPrinter = IntPtr.Zero;
        IntPtr pUnmanagedBytes = IntPtr.Zero;
        int dwWritten = 0;

        var di = new DOCINFOA();
        di.pDocName = "Laravel Ticket";
        di.pDataType = "RAW";

        try {
            if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero)) {
                errorMessage = "OpenPrinter falló. Win32: " + Marshal.GetLastWin32Error();
                return false;
            }

            if (!StartDocPrinter(hPrinter, 1, di)) {
                errorMessage = "StartDocPrinter falló. Win32: " + Marshal.GetLastWin32Error();
                return false;
            }

            if (!StartPagePrinter(hPrinter)) {
                errorMessage = "StartPagePrinter falló. Win32: " + Marshal.GetLastWin32Error();
                return false;
            }

            pUnmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);
            Marshal.Copy(bytes, 0, pUnmanagedBytes, bytes.Length);

            if (!WritePrinter(hPrinter, pUnmanagedBytes, bytes.Length, out dwWritten)) {
                errorMessage = "WritePrinter falló. Win32: " + Marshal.GetLastWin32Error();
                return false;
            }

            if (dwWritten != bytes.Length) {
                errorMessage = "Escritura incompleta al spooler. Escritos: " + dwWritten + " de " + bytes.Length;
                return false;
            }

            EndPagePrinter(hPrinter);
            EndDocPrinter(hPrinter);
            return true;
        } finally {
            if (pUnmanagedBytes != IntPtr.Zero) Marshal.FreeCoTaskMem(pUnmanagedBytes);
            if (hPrinter != IntPtr.Zero) ClosePrinter(hPrinter);
        }
    }
}
"@

$payload = [System.IO.File]::ReadAllBytes($PayloadPath)
if ($payload.Length -le 0) {
    throw "Payload vacío."
}

$errMsg = ""
$ok = [RawPrinterHelper]::SendBytesToPrinter($PrinterName, $payload, [ref]$errMsg)
if (-not $ok) {
    if ([string]::IsNullOrWhiteSpace($errMsg)) {
        throw "No se pudo enviar RAW a la impresora local."
    }
    throw $errMsg
}
PS1;

        file_put_contents($payloadPath, $payload);
        file_put_contents($scriptPathPs1, $psScript);

        try {
            $argsBase = [
                '-NoProfile',
                '-ExecutionPolicy', 'Bypass',
                '-File', $scriptPathPs1,
                '-PrinterName', $printerName,
                '-PayloadPath', $payloadPath,
                '-TempDir', $tmpDir,
            ];

            $candidates = ['powershell'];
            $finder = new ExecutableFinder();
            $pwsh = $finder->find('pwsh.exe') ?? $finder->find('pwsh');
            if (is_string($pwsh) && $pwsh !== '') {
                $candidates[] = $pwsh;
            }
            $candidates = array_values(array_unique($candidates));

            $lastDetail = '';
            foreach ($candidates as $executable) {
                $process = new Process(array_merge([$executable], $argsBase));
                $process->setTimeout(max(1, $timeoutSeconds));
                $process->run();

                if ($process->isSuccessful()) {
                    return;
                }

                $stderr = trim($process->getErrorOutput());
                $stdout = trim($process->getOutput());
                $detail = $stderr !== '' ? $stderr : $stdout;
                $lastDetail = $detail !== '' ? $this->normalizeOutputText($detail) : '';

                if (stripos($detail, '8009001d') === false) {
                    break;
                }
            }

            throw new RuntimeException('No se pudo imprimir en USB local: '.($lastDetail !== '' ? $lastDetail : 'error desconocido'));
        } finally {
            @unlink($payloadPath);
            @unlink($scriptPathPs1);
        }
    }

    private function normalizeOutputText(string $text): string
    {
        $value = trim($text);
        if ($value === '') {
            return '';
        }

        $encoded = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
        if (! is_string($encoded) || $encoded === '') {
            $encoded = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        }

        $normalized = is_string($encoded) && $encoded !== '' ? $encoded : $value;
        return preg_replace('/[^\P{C}\r\n\t]/u', '', $normalized) ?? $normalized;
    }
}
