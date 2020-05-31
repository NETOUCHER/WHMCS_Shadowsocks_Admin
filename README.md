# Shadowsocks Admin V4 [WHMCS Product Provisioning Module]

This is the provisioning module for WHMCS 7.x, compatible with Shadowsocks-manyuser (https://github.com/mengskysama/shadowsocks-rm/tree/manyuser) and its branches.  Includes a QR Code 2-D barcode generator by Dominik Dzienia.

# Full Setting Up Tutorial

Please refer to the Wiki page.

# Version Update:

4.0.0   Customizable MySQL Database Port
        Restricted Port Range (Infinity growth by default)

3.0     Added the function to change the port of a specific service: <strong>RefrePort</strong> (Per service instead of per server.).
        Added the alternative idea to update the traffic: to enlarge the limitation of the traffic rather than erasing the used traffic. Function name: <strong>AddTraffic</strong>
        Configuration rearranged. <strong>Do not support</strong> old version anymore. Module name changed to <strong>SSAdmin</strong>.
        - The ability to reset traffic when the invoice get paid and the service get renewed. Execute <strong>RstTraffic</strong> in <strong>SSAdmin_Renew</strong> to make it happen (default)

2.0     Integrated with <strong>QR code generator</strong>. *You must configure the module manually.*
        A Shadowsocks link generator.
        Converted All Mysql queries into PDO_Mysql queries.

1.0.0 <a href="https://github.com/soft-wiki/whmcs-shadowsocks">whmcs-shadowsocks</a> by Tension (Verification Request)

# Database Structure
Compatible with the structure of SSPanel.
Compatible SQL File can be found at https://github.com/NeToucher/shadowsocks-rm/tree/manyuser/shadowsocks

# Configure your module

<strong>****MOST IMPORTANT****</strong>

You must edit the URL in <strong>shadowsocks.php</strong> on line <strong>647</strong> and line <strong>652</strong>

    $imgs .= '<img src="https://example.com/modules/servers/SSAdmin/lib/QR_generator/qrcode.php?text='.$output.'" />&nbsp;';

to ensure your QR code could be loaded.

Then you could edit the domain name from <strong>example.com</strong> to yours in <strong>lib/QR_generator/qrcode.php</strong>

    if(strpos($_SERVER['HTTP_REFERER'], 'example.com') == FALSE)
       {
          QRcode::png("Stealing QR code?");
          exit;
        }

and delete the remark to enable the anti-abuse for your generator. The code in remark area could define the only domain to use the generator legally and all the visiting from the another domain will be blocked. A QR code which could be decoded to be a curse would be displayed to illegal visiting.

# Supported Configurable Options

<strong>Option Name</strong>: Traffic
<strong>Options format</strong>: {$n}G{any_text_or_not}
<strong>Example Options</strong>: 5G    10G(10% off)    20G Hot!

The module will query the value of the option named <strong>Traffic</strong>, then truncate the value by the first letter 'G'. The number before the letter 'G' would be recorded as the traffic(the traffic would be renewed when pay for the related invoice.)

# Future Features
- Graphical Statistics
- Plugins support

# LICENSING

Copyright (C) 2017-2018 NeToucher Limited

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

# ACKNOWLEDGMENTS

Including the PHP implementation of QR Code 2-D barcode generator (ver. 1.1.4)
Copyright (C) 2010 by Dominik Dzienia
http://sourceforge.net/projects/phpqrcode/

Which is based on C libqrencode library (ver. 3.1.1)
Copyright (C) 2006-2010 by Kentaro Fukuchi
http://megaui.net/fukuchi/works/qrencode/index.en.html

QR Code is registered trademarks of DENSO WAVE INCORPORATED in JAPAN and other
countries.

Reed-Solomon code encoder is written by Phil Karn, KA9Q.
Copyright (C) 2002, 2003, 2004, 2006 Phil Karn, KA9Q
