# WHMCS-Shadowsocks [Provisioning Module]

This is the provisioning module of WHMCS 7.x for shadowsocks-manyuser. It's an PHP module including a QR Code 2-D barcode generator by Dominik Dzienia.

# Version Update:

2.0.2 Integrated with <strong>QR code generator</strong>. *You must configure the module manually.*

2.0.1 A Shadowsocks link generator.

2.0.0 Converted All Mysql queries into PDO_Mysql queries.

1.0.0 <a href="https://github.com/soft-wiki/whmcs-shadowsocks">whmcs-shadowsocks</a> by Tension (Verification Request)

#

SQL File can be found at https://github.com/NeToucher/shadowsocks-rm/tree/manyuser/shadowsocks

# Configure your module

<strong>****MOST IMPORTANT****</strong>

You must edit the URL in <strong>shadowsocks.php</strong> on line <strong>540</strong> and line <strong>545</strong>

    $imgs .= '<img src="https://example.com/modules/servers/shadowsocks/QR/qrcode.php?text='.$output.'" />&nbsp;';

to ensure your QR code could be loaded. |

Then you could edit the domain name from <strong>example.com</strong> to yours in <strong>QR/qrcode.php</strong>

    if(strpos($_SERVER['HTTP_REFERER'], 'example.com') == FALSE)
       {
          QRcode::png("Fuck your self idiot.");
          exit;
        }

and clear the remark to enable the anti-abuse for your generator. The code in remark area could define the domain which could use the generator legally and all the visiting from the other domain will be block with another QR code which could be decoded to a curse.

# LICENSING

Copyright (C) 2017 NeToucher Limited

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
