# -*- coding: utf-8 -*-

import qsh
import sys

"""
Преобразует файл в формате qscalp переданный на stdin в набор строк
Использовать так:
zcat ./2020-04-03.file | python tofile.py
"""

with qsh.open( sys.stdin.fileno() ) as qsh_file:
    header=qsh_file.header
    print "# Signature: {0}".format(header.signature)
    print "# Version: {0}".format(header.version)
    print "# Application: {0}".format(header.application)
    print "# Comment: {0}".format(header.comment)
    print "# Created at: {0}".format(header.created_at)
    print "# Streams count: {0}".format(header.streams_count)

    crea=header.created_at
    stream_type, instrument_code = qsh_file.read_stream_header()
    print "# instrument_code: {0}".format(instrument_code)

    if stream_type != qsh.StreamType.DEALS:
        sys.exit("not DEALS stream")

    print "#TIMESTAMP_MSK____\t________id\t1KUPI_2PRODAY\tprice\tvolume\toi\torder_id"

    # Read frame header & frame data for one stream case
    try:
        while True:
            frame_timestamp, _ = qsh_file.read_frame_header()
            deal_entry = qsh_file.read_deals_data()
            frame_date=frame_timestamp.strftime("%Y-%m-%d")

            if True and deal_entry : 
# DealEntryNamedTuple
# id=3150572851,                     
# type 1 - покупка B  2 - продажа S
# timestamp - время. Вместо даты 1.1.1 зона /usr/share/zoneinfo/Europe/Moscow
# price 1022 - цена в тиках
# volume - объем в лотах
# oi - oi (0 для газпрома, не ноль для фьчерсов)
# order_id - какой-то другой заказа, возможно будет иметь значение в другой ценной бумаге
                    tsob=deal_entry.timestamp
                    ts="{0:0>2}:{1:0>2}:{2:0>2}".format(tsob.hour, tsob.minute,  tsob.second)
                    print "{2} {1}\t{0.id}\t{0.type}\t{0.price}\t{0.volume}\t{0.oi}\t{0.order_id}".format(deal_entry, ts, frame_date)

    except EOFError:
        pass

