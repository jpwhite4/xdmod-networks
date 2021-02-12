#!/usr/bin/env expect
# Configure a test resource for a new XDMoD instance

# Load helper functions
source [file join [file dirname [info script]] ../../../xdmod/tests/ci/scripts/helper-functions.tcl]

set timeout 240
spawn "xdmod-setup"

# Add an OnDemand resource
selectMenuOption 4

selectMenuOption 1
provideInput {Resource Name:} hermes
provideInput {Formal Name:} {Grid FTP instance}
provideInput {Resource Type*} Grid
provideInput {How many nodes does this resource have?} 0
provideInput {How many total processors (cpu cores) does this resource have?} 0

selectMenuOption s
confirmFileWrite yes
enterToContinue
confirmFileWrite yes
enterToContinue

selectMenuOption q

lassign [wait] pid spawnid os_error_flag value
exit $value
