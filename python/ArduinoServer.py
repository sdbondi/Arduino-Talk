#!/usr/bin/python

import requests
import serial
import platform
import sys
import getopt
import socket
import json

_WINDOWS = (platform.system() == 'Windows')
_AJAXURL = 'http://%(routerHost)s/comet-arduino/ajax/%(action)s/'

class ArduinoCommandServer(object):
  def __init__(self, sc, opts):
    if not sc:
      raise ValueError('Serial connection required')

    self.serial = sc
    self.options = opts or {}

  def get_incoming_commands(self):
    global _AJAXURL;
    opts = self.options
    url = _AJAXURL % { 'routerHost': opts['routerHost'], 'action': 'get_web_data'}

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

  def processCommands(self, commands):
    return range(0, len(commands))

  def sendResponse(self, batch_id, results):
    global _AJAXURL;
    opts = self.options
    url = _AJAXURL % { 'routerHost': opts['routerHost'], 'action': 'put_ar_data'}

    print 'Sending results to %s' % url   
    data = { 'args' : { 'id': batch_id, 'results': results }}

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
      print 'Waiting for remote commands from %s' % opts['routerHost']
      
      batch_id, commands = self.get_incoming_commands()

      print 'Got command id', batch_id, commands

      results = self.processCommands(commands)

      self.sendResponse(batch_id, results)
      

def get_opts(args):
  global _WINDOWS
  try:
    opts, args = getopt.getopt(args, '', ['routerHost=', 'baud=', 'serialPort='])
  except getopt.GetoptError, err:
    print str(err)
    sys.exit(2)

  optsmap = {
    'routerHost': socket.gethostname(),
    'baud': 9600,
    'serialPort': not _WINDOWS and '/dev/ttyACM0'
    }
  
  for o, a in opts:
    if o  == '--routerHost':
      optsmap['routerHost'] = a    
    elif o == "--baud":
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
    print 'Please ensure your Arduino is connected'
    sys.exit(2)

  if not sc.isOpen():
    print 'Unable to open serial connection to Arduino'
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
