iptables -I FORWARD -p tcp -m ipp2p --bit -j DROP
iptables -I FORWARD -p udp -m ipp2p --bit -j DROP
iptables -I FORWARD -m string --algo bm --string "BitTorrent" -j DROP
iptables -I FORWARD -m string --algo bm --string "BitTorrent protocol" -j DROP
iptables -I FORWARD -m string --algo bm --string "peer_id=" -j DROP
iptables -I FORWARD -m string --algo bm --string ".torrent" -j DROP
iptables -I FORWARD -m string --algo bm --string "announce.php?passkey=" -j DROP
iptables -I FORWARD -m string --algo bm --string "torrent" -j DROP
iptables -I FORWARD -m string --algo bm --string "announce" -j DROP
iptables -I FORWARD -m string --algo bm --string "info_hash" -j DROP
iptables -I OUTPUT -p tcp -m ipp2p --bit -j DROP
iptables -I OUTPUT -p udp -m ipp2p --bit -j DROP
