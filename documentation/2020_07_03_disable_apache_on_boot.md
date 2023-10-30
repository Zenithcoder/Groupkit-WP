# Disable Apache2 on boot

## Problem
It seems that apache had a priority over nginx on server boot and we end up running apache instead of nginx.

## Solution
Disable apache2 service from running upon booting. To accomplish this, one just needs to run: `service apache2 disable` on both staging and production ec2 instances.
