import time
import requests
import random

with open('url.cfg', 'r') as urlfile:
    url=urlfile.read().replace('\n','')
print('Using URL %s' % url)
try:
    while True:
        payload = {'text':'It\'s '+time.strftime('%I:%M')}
        r = requests.post(url, json=payload)
        print('Message sent. Response is %s' % r.text)
        wait = random.randrange(30,18000,1)
        print("Sleeping for %d" % wait)
        time.sleep(wait)
except KeyboardInterrupt:
    print("Done")
