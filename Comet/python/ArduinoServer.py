#!/usr/bin/python

import human_curl as requests
import serial
import platform
import sys
import getopt
import socket
import json
import time

_WINDOWS = (platform.system() == 'Windows')
_AJAXURL = 'http://localhost/arduino/comet-router.php?action=%(action)s'
#_AJAXURL = 'http://themousepotatowebsite.co.za/experiments/arduino/comet-router.php?action=%(action)s'
#_AUTH = ('stanb', 'arduino1')
_AUTH=None
_CHAROFFSET = 32
_CMDMAP = {
  'ping'        : chr(_CHAROFFSET + 0),
  'pinMode'     : chr(_CHAROFFSET + 1),
  'digitalWrite': chr(_CHAROFFSET + 2),
  'digitalRead' : chr(_CHAROFFSET + 3),
  'analogWrite' : chr(_CHAROFFSET + 4),
  'analogRead'  : chr(_CHAROFFSET + 5),

  'beep'        : chr(_CHAROFFSET + 11)
}

class ArduinoCommandServer(object):
  def __init__(self, sc, opts):
    if not sc:
      raise ValueError('Serial connection required')

    self.serial = sc
    self.options = opts or {}

  def getIncomingCommands(self):
    global _AJAXURL, _AUTH
    opts = self.options
    url = _AJAXURL % { 'action': 'get_web_data'}

    while True:     

      while True:
        try:
          resp = requests.get(url, timeout=70, auth=_AUTH)
          break;
        except requests.exceptions.CurlError as ex:
          print 'ERROR ', ex.message, ' Retrying...'
        #except requests.exceptions.Timeout:
        #  print 'Get request timed out. Retrying...'

      if resp.status_code != 200 or resp.content == False:
        print 'ERROR: status_code %d or no content' % resp.status_code
        continue
      
      obj = json.loads(resp.content);
      if obj == False:
        print 'ERROR: content parse error'
        print resp.content
        continue

      if obj['state'] != 'OK':
        print 'ERROR: ', obj['message']
        continue;

      if obj['result'] == 'TMOUT':
        continue
      
      return obj['result']

  def toArduinoCommand(self, command):
    global _CMDMAP, _CHAROFFSET

    if not command['command'] in _CMDMAP:
      print 'Unrecognised command: ', command['command']
      return False

    op_chr = _CMDMAP[command['command']]

    if 'pin' in command:
      pin = str(command['pin'])
      if pin[0] == 'A':
        pin = 14 + int(pin[1])

      pin = int(pin)

      result = op_chr+chr(pin + _CHAROFFSET)

    if 'mode' in command:
      result += 'i' if command['mode'] == 'input' else 'o'

    if 'args' in command and isinstance(command['args'], list):
      command['args'] = [str(c) for c in command['args']]
      result += '-'.join(command['args'])

    return result+'\n'

  def toWeb(self, ar_cmd):
    op_chr = ar_cmd[0]

    if op_chr == 'A':
      return 'ACK'

    if op_chr == 'R':
      return int(ar_cmd[1:])

    if op_chr == 'F':
      return { 'error': ar_cmd[1:] }

    return False

  def processCommands(self, commands):
    results = []
    for command in commands:      
      cmd_str = self.toArduinoCommand(command)

      if not cmd_str:
        results.append(False)
        continue      
            
      ar_reply = ''
      i = 0
      while len(ar_reply) == 0:
        if i % 10 == 0:
          self.serial.write(cmd_str)        
        time.sleep(0.1)
        ar_reply = self.serial.readline()
        i += 1

      functionStr = command['command']+'('
      if 'pin' in command:
        functionStr += str(command['pin'])

      if 'args' in command and isinstance(command['args'], list):
        if 'pin' in command:
          functionStr += ', '
        functionStr += ', '.join(command['args'])

      print functionStr + ') -> ' + ar_reply.strip()
      
      results.append(self.toWeb(ar_reply))

    return results

  def sendResponse(self, batch_id, results):
    global _AJAXURL, _AUTH
    opts = self.options
    url = _AJAXURL % { 'action': 'put_ar_data'}

    data = { 'object' : json.dumps({ 'id': batch_id, 'object': results })}    

    while True:
      try:
        resp = requests.post(url, data, timeout=10, auth=_AUTH)
        break;
      except requests.exceptions.CurlError as ex:
        print 'ERROR ', ex.message, ' Retrying...'
      #except requests.exceptions.Timeout:
      #  print 'Send request timed out. Retrying...'

    if resp.status_code != 200 or resp.content == False:
      print 'ERROR: status_code %d or no content' % resp.status_code
      return False
    
    obj = json.loads(resp.content);
    if obj == False:
      print 'ERROR: content parse error'
      print resp.content
      return False

    if obj['state'] != 'OK':
      print 'ERROR: ', obj['message']
      return False

    if obj['result'] == 'TMOUT':
      return False

    if obj['result'] == 'PASS':
      return True
    
    print 'Got unknown result: ', obj      
    return False

  def start(self):
    opts = self.options

    while True:      
      print 'Waiting for incoming commands...'
      results = self.getIncomingCommands()
      
      print '================================'
      print 'Got command(s).'
      for _object in results:
        batch_id = _object['id']
        commands = _object['object']
        print 'Batch ID: %d. Processing...' % batch_id
        results = self.processCommands(commands)
        print 'Sending reply...'

        self.sendResponse(batch_id, results)
        print 'Done'
        print '================================\n\n'


def get_opts(args):
  global _WINDOWS
  try:
    opts, args = getopt.getopt(args, '', ['baud=', 'serialPort='])
  except getopt.GetoptError, err:
    print str(err)
    sys.exit(2)

  optsmap = {
    'baud': 9600,
    'serialPort': not _WINDOWS and '/dev/ttyACM0'
    }
  
  for o, a in opts: 
    if o == "--baud":
      optsmap['baud'] = int(a)
    elif o == "--serialPort":
      optsmap['serialPort'] = a
    else:
      assert False, "unhandled option"

  if optsmap['serialPort'] == False:
    raise ValueError('Argument --serialPort= is mandatory')   

  return optsmap

def main(args):
  opts = get_opts(args)

  # Check for arduino serial port
  try: 
    sc = serial.Serial(opts['serialPort'], opts['baud'], timeout=0)
  except serial.SerialException, err:
    print str(err)
    print 'Please ensure your Arduino is connected and the port is correct.'
    sys.exit(2)

  if not sc.isOpen():
    print 'Unable to open serial connection to Arduino.'
    sys.exit(1)

  print 'Connected to serial on', opts['serialPort']
  
  try:
    # Start relay server
    while 1:
      server = ArduinoCommandServer(sc, opts)
      server.start()
  finally:
    if sc and sc.isOpen():
      sc.close()
  
if __name__ == '__main__':
  main(sys.argv[1:])
