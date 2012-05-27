// vi:set et ts=4:
// input: "YYYY-MM-DD hh:mm:ss url status size response_time_micros"
// Description: filters on time-slices of requests with counts and sum of response-time

import java.io.*;
import java.util.*;
import java.util.regex.*;

class Calculate {
    private static final int ALL = 0;
    private static final int TOP = 1;
    private static final int MOST = 2;
    private static final int MORE = 3;
    private static final int SOME = 4;
    private static final int BLUB = 5;
    private static final int REM = 6;
    private static final int MAX = 6;
    private static final int IDXCNT = 7;

    public static float perc(long value, long sum) {
       float result = (float) Math.floor((float)value / (float)sum * 10000) / 100;
       return result;
    }

    public static void main(String[] args) throws Exception {
        String[] TITLE = new String[] {
            "TOTAL  ",
            "T>10000",
            "T>5000 ",
            "T>2000 ",
            "T>1000 ",
            "T>500  ",
            "T<=500 ",
        };

        long DBG = 0;
        String add, line;

        long AVG[] = new long[IDXCNT], TIME[] = new long[IDXCNT], CNT[] = new long[IDXCNT];
        float P_CNT[] = new float[IDXCNT], P_TIME[] = new float[IDXCNT];
        for( int i=0; i < IDXCNT; i++) {
            AVG[i] = TIME[i] = CNT[i] = 0;
            P_CNT[i] = P_TIME[i] = 0;
        }

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

            TIME[ALL] += time;
            CNT[ALL]++;

            if( time > 10000000 ) {
                TIME[TOP] += time;
                CNT[TOP]++;
                add = "\tTOP";
            }
            else if( time > 5000000 ) {
                TIME[MOST] += time;
                CNT[MOST]++;
                add = "\tMOST";
            }
            else if( time > 2000000 ) {
                TIME[MORE] += time;
                CNT[MORE]++;
                add = "\tMORE";
            }
            else if( time > 1000000 ) {
                TIME[SOME] += time;
                CNT[SOME]++;
                add = "\tSOME";
            }
            else if( time > 500000 ) {
                TIME[BLUB] += time;
                CNT[BLUB]++;
                add = "\tBLUB";
            }
            else {
                TIME[REM] += time;
                CNT[REM]++;
                add = "\tREM";
            }
        }

        for( int i=0; i < IDXCNT; i++) {
            if (CNT[i] > 0 )
                AVG[i] = TIME[i] / CNT[i];

            P_CNT[i]  = perc(CNT[i], CNT[ALL]);
            P_TIME[i] = perc(TIME[i], TIME[ALL]);
        }

        System.out.println();
        System.out.println("LEGEND   :      COUNT                         TIME               AVG-TIME [micro-sec]");
        for( int i=1; i < IDXCNT; i++) {
            print( TITLE[i], CNT[i], P_CNT[i], TIME[i], P_TIME[i], AVG[i] );
        }
        print( TITLE[0], CNT[0], P_CNT[0], TIME[0], P_TIME[0], AVG[0] );
        System.out.println();
        System.out.println();
    }

    public static void print( String title, long cnt, float p_cnt, long time, float p_time, long avg ) {
        StringBuilder buf = new StringBuilder();
        Formatter formatter = new Formatter(buf, Locale.US);
        formatter.format("%-8s : %,10d (%6.2f%%)   %,16d (%6.2f%%)   %,10d", title,  cnt, p_cnt,  time, p_time,  avg );
        System.out.println(buf.toString());
    }

}

