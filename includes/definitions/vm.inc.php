<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage definitions
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2016 Observium Limited
 *
 */

// Agent 'virt-what' script label mapping
$config['virt-what']['vmware'] = "VMware Virtual Machine";
$config['virt-what']['virtualpc'] = "VirtualPC Virtual Machine";
$config['virt-what']['virtualbox'] = "Virtualbox Virtual Machine";
$config['virt-what']['hyperv'] = "Hyper-V Virtual Machine";
$config['virt-what']['openvz'] = "OpenVZ Container";
$config['virt-what']['lxc'] = "LXC Container";
$config['virt-what']['uml'] = "User Mode Linux Virtual Machine";
$config['virt-what']['linux_vserver'] = "Linux-Vserver Machine";
$config['virt-what']['linux_vserver-host'] = "Linux-Vserver Host";
$config['virt-what']['linux_vserver-guest'] = "Linux-Vserver Guest";
$config['virt-what']['powervm_lx86'] = "IBM PowerVM Lx86 Virtual Machine";
$config['virt-what']['virtage'] = "Hitachi Virtualization Manager Virtual Machine";
$config['virt-what']['ibm_systemz'] = "IBM SystemZ Virtual Machine";
$config['virt-what']['ibm_systemz-zvm'] = "IBM SystemZ ZVM Virtual Machine";
$config['virt-what']['ibm_systemz-lpar'] = "IBM SystemZ LPAR Virtual Machine";
$config['virt-what']['ibm_systemz-direct'] = "IBM SystemZ Direct Virtual Machine";
$config['virt-what']['parallels'] = "Parallels Virtual Machine";
$config['virt-what']['xen'] = "Xen Virtual Machine";
$config['virt-what']['xen-hvm'] = "Xen HVM Virtual Machine";
$config['virt-what']['xen-dom0'] = "Xen Virtual Machine Host (dom0)";
$config['virt-what']['xen-domU'] = "Xen Virtual Machine (domU)";
$config['virt-what']['virt'] = "Virtual Machine";
$config['virt-what']['qemu'] = "QEMU Virtual Machine";
$config['virt-what']['kvm'] = "KVM Virtual Machine";
// Agent 'os' script label mapping
$config['virt-what']['xen:hvm'] = "Xen HVM Virtual Machine";
$config['virt-what']['xen:pv'] = "Xen Virtual Machine (domU)";
$config['virt-what']['bhyve'] = "BSD Hypervisor";
$config['virt-what']['microsoft'] = "Hyper-V Virtual Machine";
$config['virt-what']['jail'] = "Jail";

/**
 * Translate the VMware guestId properties to their
 * corresponding full OS name.
 */

$config['vmware_guestid']['asianux3_64Guest'] = 'Asianux Server 3 (64 bit)';
$config['vmware_guestid']['asianux3Guest'] = 'Asianux Server 3';
$config['vmware_guestid']['asianux4_64Guest'] = 'Asianux Server 4 (64 bit)';
$config['vmware_guestid']['asianux4Guest'] = 'Asianux Server 4';
$config['vmware_guestid']['centos64Guest'] = 'CentOS 4/5 (64-bit)';
$config['vmware_guestid']['centosGuest'] = 'CentOS 4/5';
$config['vmware_guestid']['darwin64Guest'] = 'Mac OS 10.5 (64 bit)';
$config['vmware_guestid']['darwinGuest'] = 'Mac OS 10.5';
$config['vmware_guestid']['debian4_64Guest'] = 'Debian GNU/Linux 4 (64 bit)';
$config['vmware_guestid']['debian4Guest'] = 'Debian GNU/Linux 4';
$config['vmware_guestid']['debian5_64Guest'] = 'Debian GNU/Linux 5 (64 bit)';
$config['vmware_guestid']['debian5Guest'] = 'Debian GNU/Linux 5';
$config['vmware_guestid']['dosGuest'] = 'MS-DOS.';
$config['vmware_guestid']['eComStationGuest'] = 'eComStation';
$config['vmware_guestid']['freebsd64Guest'] = 'FreeBSD x64';
$config['vmware_guestid']['freebsdGuest'] = 'FreeBSD';
$config['vmware_guestid']['mandriva64Guest'] = 'Mandriva Linux (64 bit)';
$config['vmware_guestid']['mandrivaGuest'] = 'Mandriva Linux';
$config['vmware_guestid']['netware4Guest'] = 'Novell NetWare 4';
$config['vmware_guestid']['netware5Guest'] = 'Novell NetWare 5.1';
$config['vmware_guestid']['netware6Guest'] = 'Novell NetWare 6.x';
$config['vmware_guestid']['nld9Guest'] = 'Novell Linux Desktop 9';
$config['vmware_guestid']['oesGuest'] = 'Open Enterprise Server';
$config['vmware_guestid']['openServer5Guest'] = 'SCO OpenServer 5';
$config['vmware_guestid']['openServer6Guest'] = 'SCO OpenServer 6';
$config['vmware_guestid']['oracleLinux64Guest'] = 'Oracle Linux 4/5 (64-bit)';
$config['vmware_guestid']['oracleLinuxGuest'] = 'Oracle Linux 4/5';
$config['vmware_guestid']['os2Guest'] = 'OS/2';
$config['vmware_guestid']['other24xLinux64Guest'] = 'Linux 2.4x Kernel (64 bit) (experimental)';
$config['vmware_guestid']['other24xLinuxGuest'] = 'Linux 2.4x Kernel';
$config['vmware_guestid']['other26xLinux64Guest'] = 'Linux 2.6x Kernel (64 bit) (experimental)';
$config['vmware_guestid']['other26xLinuxGuest'] = 'Linux 2.6x Kernel';
$config['vmware_guestid']['otherGuest'] = 'Other Operating System';
$config['vmware_guestid']['otherGuest64'] = 'Other Operating System (64 bit) (experimental)';
$config['vmware_guestid']['otherLinux64Guest'] = 'Linux (64 bit) (experimental)';
$config['vmware_guestid']['otherLinuxGuest'] = 'Other Linux';
$config['vmware_guestid']['redhatGuest'] = 'Red Hat Linux 2.1';
$config['vmware_guestid']['rhel2Guest'] = 'Red Hat Enterprise Linux 2';
$config['vmware_guestid']['rhel3_64Guest'] = 'Red Hat Enterprise Linux 3 (64 bit)';
$config['vmware_guestid']['rhel3Guest'] = 'Red Hat Enterprise Linux 3';
$config['vmware_guestid']['rhel4_64Guest'] = 'Red Hat Enterprise Linux 4 (64 bit)';
$config['vmware_guestid']['rhel4Guest'] = 'Red Hat Enterprise Linux 4';
$config['vmware_guestid']['rhel5_64Guest'] = 'Red Hat Enterprise Linux 5 (64 bit) (experimental)';
$config['vmware_guestid']['rhel5Guest'] = 'Red Hat Enterprise Linux 5';
$config['vmware_guestid']['rhel6_64Guest'] = 'Red Hat Enterprise Linux 6 (64 bit)';
$config['vmware_guestid']['rhel6Guest'] = 'Red Hat Enterprise Linux 6';
$config['vmware_guestid']['sjdsGuest'] = 'Sun Java Desktop System';
$config['vmware_guestid']['sles10_64Guest'] = 'Suse Linux Enterprise Server 10 (64 bit) (experimental)';
$config['vmware_guestid']['sles10Guest'] = 'Suse linux Enterprise Server 10';
$config['vmware_guestid']['sles11_64Guest'] = 'Suse Linux Enterprise Server 11 (64 bit)';
$config['vmware_guestid']['sles11Guest'] = 'Suse linux Enterprise Server 11';
$config['vmware_guestid']['sles64Guest'] = 'Suse Linux Enterprise Server 9 (64 bit)';
$config['vmware_guestid']['slesGuest'] = 'Suse Linux Enterprise Server 9';
$config['vmware_guestid']['solaris10_64Guest'] = 'Solaris 10 (64 bit) (experimental)';
$config['vmware_guestid']['solaris10Guest'] = 'Solaris 10 (32 bit) (experimental)';
$config['vmware_guestid']['solaris6Guest'] = 'Solaris 6';
$config['vmware_guestid']['solaris7Guest'] = 'Solaris 7';
$config['vmware_guestid']['solaris8Guest'] = 'Solaris 8';
$config['vmware_guestid']['solaris9Guest'] = 'Solaris 9';
$config['vmware_guestid']['suse64Guest'] = 'Suse Linux (64 bit)';
$config['vmware_guestid']['suseGuest'] = 'Suse Linux';
$config['vmware_guestid']['turboLinux64Guest'] = 'Turbolinux (64 bit)';
$config['vmware_guestid']['turboLinuxGuest'] = 'Turbolinux';
$config['vmware_guestid']['ubuntu64Guest'] = 'Ubuntu Linux (64 bit)';
$config['vmware_guestid']['ubuntuGuest'] = 'Ubuntu Linux';
$config['vmware_guestid']['unixWare7Guest'] = 'SCO UnixWare 7';
$config['vmware_guestid']['win2000AdvServGuest'] = 'Windows 2000 Advanced Server';
$config['vmware_guestid']['win2000ProGuest'] = 'Windows 2000 Professional';
$config['vmware_guestid']['win2000ServGuest'] = 'Windows 2000 Server';
$config['vmware_guestid']['win31Guest'] = 'Windows 3.1';
$config['vmware_guestid']['win95Guest'] = 'Windows 95';
$config['vmware_guestid']['win98Guest'] = 'Windows 98';
$config['vmware_guestid']['windows7_64Guest'] = 'Windows 7 (64 bit)';
$config['vmware_guestid']['windows7Guest'] = 'Windows 7';
$config['vmware_guestid']['windows7Server64Guest'] = 'Windows Server 2008 R2 (64 bit)';
$config['vmware_guestid']['winLonghorn64Guest'] = 'Windows Longhorn (64 bit) (experimental)';
$config['vmware_guestid']['winLonghornGuest'] = 'Windows Longhorn (experimental)';
$config['vmware_guestid']['winMeGuest'] = 'Windows Millenium Edition';
$config['vmware_guestid']['winNetBusinessGuest'] = 'Windows Small Business Server 2003';
$config['vmware_guestid']['winNetDatacenter64Guest'] = 'Windows Server 2003, Datacenter Edition (64 bit) (experimental)';
$config['vmware_guestid']['winNetDatacenterGuest'] = 'Windows Server 2003, Datacenter Edition';
$config['vmware_guestid']['winNetEnterprise64Guest'] = 'Windows Server 2003, Enterprise Edition (64 bit)';
$config['vmware_guestid']['winNetEnterpriseGuest'] = 'Windows Server 2003, Enterprise Edition';
$config['vmware_guestid']['winNetStandard64Guest'] = 'Windows Server 2003, Standard Edition (64 bit)';
$config['vmware_guestid']['winNetStandardGuest'] = 'Windows Server 2003, Standard Edition';
$config['vmware_guestid']['winNetWebGuest'] = 'Windows Server 2003, Web Edition';
$config['vmware_guestid']['winNTGuest'] = 'Windows NT 4';
$config['vmware_guestid']['winVista64Guest'] = 'Windows Vista (64 bit)';
$config['vmware_guestid']['winVistaGuest'] = 'Windows Vista';
$config['vmware_guestid']['winXPHomeGuest'] = 'Windows XP Home Edition';
$config['vmware_guestid']['winXPPro64Guest'] = 'Windows XP Professional Edition (64 bit)';
$config['vmware_guestid']['winXPProGuest'] = 'Windows XP Professional';

// EOF
