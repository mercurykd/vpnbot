import sys
from PyQt5.QtCore import *


def enc(s):
    ba = qCompress(QByteArray(s.encode()))
    ba = ba.toBase64(QByteArray.Base64Option.Base64UrlEncoding | QByteArray.Base64Option.OmitTrailingEquals)
    print('vpn://' + str(ba, 'utf-8'))

s = ''
for line in sys.stdin:
    s += line
s = s.strip()
enc(s)
