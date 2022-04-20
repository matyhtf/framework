//gcc -g -o struct struct.c
#include <stdlib.h>
#include <stdio.h>
#include <stdint.h>
#include <string.h>

#pragma pack(4)

int main(int argc, char **argv)
{
    if (argc < 2)
    {
        printf("./struct [filename]\n");
    }
    int n_fread;
    if (argc == 2)
    {
           n_fread = 1;     
    }
    else
    {
         n_fread = atoi(argv[2]);
    }
    struct 
    {
        int32_t id;
        char data[40];
        double price;
        float price2;
        int64_t count;        
    } data;
    
    int n, i;
    FILE *fp;
    if (fp=fopen(argv[1],"rb"))
    {
        for(i =0; i<n_fread; i++)
        {
            n = fread(&data, sizeof(data), 1, fp);
            if (n < 0)
            {
                break;
            }
            printf("#%d\tid=%d\tdata=%s\tprice=%.2f\tprirce2=%.2f\tcount=%ld\n", 
                i, data.id, data.data, data.price, data.price2, data.count);
        }
    }
    return 0;
}
