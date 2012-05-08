#!/usr/bin/python

import requests
import serial
import platform
import sys
import getopt
import socket
import json
import time

_WINDOWS = (platform.system() == 'Windows')
_AJAXURL = 'http://10.0.0.106/comet-arduino/ajax/%(action)s/'
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
    global _AJAXURL;
    opts = self.options
    url = _AJAXURL % { 'action': 'get_web_data'}

    print 'Getting from %s' % url   
    while True:     
      resp = requests.get(url)

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
      
      print 'Got object: ', obj
      result = obj['result']
      return result['id'], result['payload'];

  def toArduinoCommand(self, command):
    global _CMDMAP, _CHAROFFSET
    print command
    if not command['command'] in _CMDMAP:
      print 'Unrecognised command: ', command['command']
      return False

    op_chr = _CMDMAP[command['command']]
    pin = str(command['pin'])
    if pin[0] == 'A':
      pin = 14 + int(pin[1])

    pin = int(pin)

    result = op_chr+chr(pin + _CHAROFFSET)

    if 'mode' in command:
      result += 'i' if command['mode'] == 'input' else 'o'

    if 'value' in command:
      result += str(command['value'])
    print "COMMAND ", result
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

      self.serial.write(cmd_str)
      
      ar_reply = self.serial.readline()      
      while len(ar_reply) == 0:
        time.sleep(0.1)
        ar_reply = self.serial.readline()

      results.append(self.toWeb(ar_reply))

    return results

  def sendResponse(self, batch_id, results):
    global _AJAXURL
    opts = self.options
    url = _AJAXURL % { 'action': 'put_ar_data'}

    print 'Sending results to %s' % url   
    data = { 'args' : json.dumps({ 'id': batch_id, 'results': results })}    
    resp = requests.post(url, data)

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
      batch_id, commands = self.getIncomingCommands()

      print 'Got command id', batch_id, commands

      results = self.processCommands(commands)
      print 'Got results: ', results

      self.sendResponse(batch_id, results)
      

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
