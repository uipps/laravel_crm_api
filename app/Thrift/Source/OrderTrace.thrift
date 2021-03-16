namespace php App.Thrift.Protocol.Order


service OrderTrace
{
    string trace(1:string trace_no);
}
