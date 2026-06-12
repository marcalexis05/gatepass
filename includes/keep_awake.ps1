# GatePass Pro - Sleep Prevention Utility
# Prevents Windows from going into sleep mode while the tunnel is active.

param(
    [int]$ParentPid = 0
)

$code = @'
using System;
using System.Runtime.InteropServices;

public class SleepPreventer {
    [DllImport("kernel32.dll", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern uint SetThreadExecutionState(uint esFlags);
}
'@

try {
    Add-Type -TypeDefinition $code -ErrorAction SilentlyContinue
} catch {
    # Already loaded or failed silently
}

# ES_CONTINUOUS (0x80000000) | ES_SYSTEM_REQUIRED (0x00000001) | ES_AWAYMODE_REQUIRED (0x00000040)
# This flags the system that a thread is active and requires system power.
$flags = [System.UInt32]2147483713
[SleepPreventer]::SetThreadExecutionState($flags)

Write-Output "Keep-awake script active. System sleep is suppressed."
if ($ParentPid -gt 0) {
    Write-Output "Monitoring parent PHP process: $ParentPid"
}

# Keep the script running to hold the thread active
while ($true) {
    # If a parent PID was specified, check if the process is still running
    if ($ParentPid -gt 0) {
        $parent = Get-Process -Id $ParentPid -ErrorAction SilentlyContinue
        if ($null -eq $parent) {
            Write-Output "Parent PHP process ($ParentPid) exited. Cleaning up keep-awake job."
            break
        }
    }
    
    Start-Sleep -Seconds 10
    [SleepPreventer]::SetThreadExecutionState($flags)
}
