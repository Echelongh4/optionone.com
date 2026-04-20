# Windows Auto-Start Guide

This guide explains how to make the local XAMPP installation start automatically after the computer powers on.

Important:
- the application cannot run when the computer is off
- the best you can do on a local machine is make Apache and MySQL start automatically when Windows starts

## Goal

After a reboot:
- Apache starts automatically
- MySQL starts automatically
- the local app becomes available without opening XAMPP manually

## Step 1: Open XAMPP Control Panel As Administrator

1. Close XAMPP if it is already open.
2. Right-click the XAMPP Control Panel shortcut.
3. Choose `Run as administrator`.

## Step 2: Install Apache and MySQL As Services

In the XAMPP Control Panel:

1. Find `Apache`
2. Find `MySQL`
3. Click the `Svc` checkbox beside both services

This installs them as Windows services.

## Step 3: Set Services To Automatic

1. Press `Win + R`
2. Run:

```txt
services.msc
```

3. Find the Apache service, usually `Apache2.4`
4. Find the MySQL service, usually `mysql` or `MySQL`
5. Open each service
6. Set `Startup type` to `Automatic`
7. Click `Apply`
8. Click `Start` if the service is not already running

## Step 4: Reboot Test

1. Restart the computer
2. Wait for Windows to finish loading
3. Open the local URL in a browser:

```txt
http://optionone.test/login
```

If the page opens, auto-start is working.

## Important Limits

- if the PC is shut down, the app is unavailable
- if the PC loses power, the app is unavailable
- if Apache or MySQL fails to start, the app will not load
- XAMPP is acceptable for local office use, but not ideal for strict 24/7 production hosting

## Better Always-On Options

If the client wants the app available all the time, use one of these:

- a dedicated office PC that stays on
- a mini PC or local server
- a VPS or cloud server

## Recommended Final Check

- Apache service is running
- MySQL service is running
- browser opens `/login`
- another LAN device can still access the app if needed
