// vi:set et ts=4:
// input: "YYYY-MM-DD hh:mm:ss url status size response_time_micros"
// Description: counts number of requests and response-time, sort by response-time

import java.io.*;
import java.util.*;
import java.util.regex.*;

class CountUrls {
    public static float perc(long value, long sum) {
       float result = (float) Math.floor((float)value / (float)sum * 10000) / 100;
       return result;
    }

    static class Data {
        public long cnt;
        public long rtime;

        public Data(long rtime) {
            this.cnt = 1;
            this.rtime = rtime;
        }

        public void inc(long rtime) {
            this.cnt++;
            this.rtime += rtime;
        }
    }

    static class ValueComp implements Comparator<String> {
        private Map<String,Data> map;

        public ValueComp(Map<String,Data> map) {
            this.map = map;
        }

        public int compare(String url1, String url2) {
            Data data1 = this.map.get(url1);
            Data data2 = this.map.get(url2);
            return -Long.valueOf(data1.rtime).compareTo(data2.rtime);
        }
    }

    private static final Map<String,Data> MAP = new LinkedHashMap<String,Data>();

    public static void main(String[] args) throws Exception {
        long DBG = 0;
        String line;

        long cnt = 0, timeall = 0;

        int lcnt = 0;

        BufferedReader br = new BufferedReader(new InputStreamReader(System.in));
        while ( (line = br.readLine()) != null ) {
            lcnt++;
            // input: "YYYY-MM-DD hh:mm:ss url status size response_time_micros"
            String[] arr = line.split("\\s+");
            if (arr == null || arr.length != 6) {
                System.err.println("Skipping bad pattern found in #line "+lcnt+": ["+line+"]");
                continue;
            }
            String url = arr[2];
            int status = Integer.valueOf(arr[3]);
            int size = Integer.valueOf(arr[4]);
            long time = Long.valueOf(arr[5]); // [micro-secs]

            cnt++;
            timeall += time;
            inc(url, time);
        }

        ValueComp comparator = new ValueComp(MAP);
        Map<String,Data> sortMAP = new TreeMap<String,Data>(comparator);
        sortMAP.putAll(MAP);

        System.out.println();
        System.out.println("     COUNT                         TIME             URL");
        for( Map.Entry<String,Data> e : sortMAP.entrySet() ) {
            Data data = e.getValue();
            print( e.getKey(), data.cnt, data.rtime, cnt, timeall );
        }
        print( "TOTAL", cnt, timeall, cnt, timeall );
        System.out.println();
        System.out.println();
    }

    public static void inc( String url, long rtime ) {
        Data data = MAP.get(url);
        if( data == null ) {
            data = new Data(rtime);
            MAP.put(url, data);
        } else {
            data.inc(rtime);
        }
    }

    public static void print( String url, long cnt, long time, long cntall, long timeall ) {
        StringBuilder buf = new StringBuilder();
        Formatter formatter = new Formatter(buf, Locale.US);
        formatter.format("%,10d (%6.2f%%)   %,16d (%6.2f%%)   %s", cnt, perc(cnt,cntall),  time, perc(time,timeall),  url);
        System.out.println(buf.toString());
    }

}

