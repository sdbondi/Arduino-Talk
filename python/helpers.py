import string
import random

class Helpers:
  @staticmethod
  def rand_string(length):
    chars = string.ascii_uppercase + string.ascii_lowercase + string.digits
    return ''.join([random.choice(chars) for x in range(length)])
