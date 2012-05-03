#!/bin/python
import socket
import sys
import getopt
import time
import serial
import platform
import hashlib
import base64
import select

from helpers import Helpers

_WINDOWS = (platform.system() == 'Windows')

class ArduinoRelayServer(object):
  def __init__(self, sc, opts):
    if not sc:
      raise ValueError('Serial connection required')

    self.serial = sc
    self.options = opts or {}

    # Create socket
    self.socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

  def __handshake(self, s, auth_key):
    guid = 'b8573686-91e5-48fa-a467-985d809fad76'

    s.send(
      ('X-ARDUINO/0.1 101 Socket Protocol Handshake\r\n'
       'Sec-Socket-Key: %s\r\n'
       'Host: %s\r\n'
       'Origin: %s\r\n'
       '\r\n') % (auth_key, self.options['remoteHost'], socket.gethostname())
      )

    data = s.recv(1024)
    print data
    key_line = ''
    key = ''
    lines = data.splitlines()
    for line in lines:
      parts = line.partition(": ")
      if parts[0] == "Sec-Socket-Accept":
        key = parts[2]
      elif parts[0] == "Socket-Host":
        host = parts[2]
      elif parts[0] == "Socket-Origin":
        origin = parts[2]
      key_line = line

    if (len(key) == 0):
      raise Exception('No secure socket key recieved')

    hashstr = auth_key + guid
    hashstr = hashlib.sha1(hashstr).digest()
    hashstr = base64.b64encode(hashstr)

    print 'Recieved: %s, Correct: %s' % (key, hashstr)

    return hashstr == key

  def wait_ready(self):
    while True:
      try:
        received = self.socket.recv(1024)       
        if len(received) == 0:
          print 'Remote server disconnected'
          return False
      except socket.error:
        time.sleep(1)
        continue # Nothing received - continue
      
      print 'Recieved: ', received
      if received == '~WEBREADY':
        return True

  def start_routing(self):
    global _WINDOWS

    self.socket.setblocking(0)  

    try:
      while 1:
        data = self.serial.readline()
        
        received = ''        
        try:
          received = self.socket.recv(1024)        

          if len(received) > 0 and received[0] == '~':
            if received[1:] == 'CLOSE':
              break
        except socket.error, ex:
          # Using 'select.select' on sockets would be better than this
          # however python on windows doesn't support fileno()
          # needed by 'select.select' 
          # Perhaps a better way would be to put socket and  serial on a seperate thread
          if str(ex).find('forcibly closed') >= 0:
            break;

          if len(data) == 0:
            time.sleep(0.5)
            continue
          pass # Nothing received - continue

        if len(data) > 0:
          self.socket.send(data)
          print 'ARDUINO: ', data

        if len(received) > 0:
          if received[-1] != '\n':
            received += '\n'
          self.serial.write(received)
          print 'SERVER: ', received        
    except socket.error, ex:
      print 'Socket Error: ', ex
    finally:
      if self.socket:
        self.socket.close()

  def start(self):
    opts = self.options

    print 'Connecting to web relay on %s:%d' % (opts['remoteHost'], opts['remotePort'])
    
    connected = False
    while not connected:    
      try:
        self.socket.connect((opts['remoteHost'], opts['remotePort']))
        connected = True
        print 'Connected!'
      except socket.error:
        print 'Connection error - retrying in 5 secs...'
        time.sleep(5)
        continue

    print '-- Handshake --'
    self.__auth_key = Helpers.rand_string(16)
    if (not self.__handshake(self.socket, self.__auth_key)):
      raise Exception('Handshake failed!')

    print 'Handshake complete!'
    print 'Waiting for READY signal from server'

    self.wait_ready()

    print 'All systems go! Routing data between server and Arduino'
    self.start_routing()
    
    if self.socket != None:
      self.socket.close();
  
def get_opts(args):
  global _WINDOWS
  try:
    opts, args = getopt.getopt(args, '', ['remoteHost=', 'remotePort=', 'baud=', 'usbSerial='])
  except getopt.GetoptError, err:
    print str(err)
    sys.exit(2)

  optsmap = {
    'remoteHost': socket.gethostname(),
    'remotePort': 8080,
    'baud': 9600,
    'usbSerial': not _WINDOWS and '/dev/tty.usbserial'
    }
  
  for o, a in opts:
    if o  == '--remoteHost':
      optsmap['remoteHost'] = a
    elif o == "--remotePort":
      optsmap['remotePort'] = int(a)
    elif o == "--baud":
      optsmap['baud'] = int(a)
    elif o == "--usbSerial":
      optsmap['usbSerial'] = a
    else:
      assert False, "unhandled option"

  if optsmap['usbSerial'] == False:
    raise ValueError('Argument --usbSerial= is mandatory') 

  return optsmap

def main(args):
  opts = get_opts(args)

  # Check for arduino serial port
  try: 
    sc = serial.Serial(opts['usbSerial'], opts['baud'], timeout=0)
  except serial.SerialException, err:
    print str(err)
    print 'Please ensure your Arduino is connected'
    sys.exit(2)

  if not sc.isOpen():
    print 'Unable to open serial connection to Arduino'
    sys.exit(1)

  print 'Connected to serial on', opts['usbSerial']

  try:
    # Start relay server
    while 1:
      server = ArduinoRelayServer(sc, opts)
      server.start()
  finally:
    if sc and sc.isOpen():
      sc.close()
  
if __name__ == '__main__':
  main(sys.argv[1:])
