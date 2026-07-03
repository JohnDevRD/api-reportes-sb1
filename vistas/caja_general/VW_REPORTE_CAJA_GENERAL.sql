CREATE VIEW
    "VW_REPORTE_CAJA_GENERAL" AS
-- =========================================================================
-- 1. PAGOS EFECTUADOS
-- =========================================================================
SELECT
    'PAGO EFECTUADO' AS "ModuloOrigen",
    T0."DocNum" AS "Pago",
    T0."DocDate",
    T0."CardCode",
    T0."CardName",
    T0."CashAcct",
    CASE 
        WHEN T0."DocType" = 'C' THEN 'CLIENTE (A CUENTA)'
        WHEN T0."DocType" = 'S' AND T1."InvType" IS NULL AND T3."DocNum" IS NULL THEN 'PROVEEDOR (A CUENTA)'
        WHEN T1."InvType" = 18 THEN 'FACTURA PROVEEDOR'
        WHEN T1."InvType" = 13 THEN 'FACTURA CLIENTE'
        WHEN T3."DocNum" IS NOT NULL THEN 'CUENTA'
        ELSE 'OTROS'
    END AS "TipoDetalle",
    COALESCE(T1."DocLine", T3."LineId", 0) AS "Linea",
    T1."InvType",
    COALESCE(T2."DocNum", T5."DocNum") AS "DocumentoOrigen",
    COALESCE(T2."DocDate", T5."DocDate") AS "FechaOrigen",
    COALESCE(T2."DocTotal", T5."DocTotal") AS "TotalOrigen",
    T4."OcrCode" AS "OcrCode",
    COALESCE(T4."GTotal", T6."GTotal", T3."SumApplied", T0."NoDocSum") AS "GTotal",
    T0."Comments" AS "CommentsPago",
    COALESCE(T2."NumAtCard", T5."NumAtCard") AS "NumAtCardFactura",
    COALESCE(T2."Comments", T5."Comments") AS "CommentsFactura",
    COALESCE(T2."ObjType", T5."ObjType") AS "ObjType"
FROM OVPM T0
LEFT JOIN VPM2 T1 ON T0."DocEntry" = T1."DocNum"
LEFT JOIN OPCH T2 ON T1."DocEntry" = T2."DocEntry" AND T1."InvType" = 18
LEFT JOIN PCH1 T4 ON T2."DocEntry" = T4."DocEntry"
LEFT JOIN OINV T5 ON T1."DocEntry" = T5."DocEntry" AND T1."InvType" = 13
LEFT JOIN INV1 T6 ON T5."DocEntry" = T6."DocEntry"
LEFT JOIN VPM4 T3 ON T0."DocEntry" = T3."DocNum"
WHERE T0."CashAcct" IN ('12345678', '87654321', '12345678', '87654321', '12345678', '11223344', '44332211', '11001100', '00110011', '00112233')

UNION ALL

-- =========================================================================
-- 2. PAGOS RECIBIDOS
-- =========================================================================


SELECT
    'PAGO RECIBIDO' AS "ModuloOrigen",
    T0."DocNum" AS "Pago",
    T0."DocDate",
    T0."CardCode",
    T0."CardName",
    T0."CashAcct",
    CASE 
        WHEN T0."DocType" = 'C' AND T1."InvType" IS NULL AND T3."DocNum" IS NULL THEN 'CLIENTE (A CUENTA)'
        WHEN T0."DocType" = 'S' THEN 'PROVEEDOR (A CUENTA)'
        WHEN T1."InvType" = 18 THEN 'FACTURA PROVEEDOR'
        WHEN T1."InvType" = 13 THEN 'FACTURA CLIENTE'
        WHEN T3."DocNum" IS NOT NULL THEN 'CUENTA'
        ELSE 'OTROS'
    END AS "TipoDetalle",
    COALESCE(T1."DocLine", T3."LineId", 0) AS "Linea",
    T1."InvType",
    COALESCE(T2."DocNum", T5."DocNum") AS "DocumentoOrigen",
    COALESCE(T2."DocDate", T5."DocDate") AS "FechaOrigen",
    COALESCE(T2."DocTotal", T5."DocTotal") AS "TotalOrigen",
    T4."OcrCode" AS "OcrCode",
    COALESCE(T4."GTotal", T6."GTotal", T3."SumApplied", T0."NoDocSum") AS "GTotal",
    T0."Comments" AS "CommentsPago",
    COALESCE(T2."NumAtCard", T5."NumAtCard") AS "NumAtCardFactura",
    COALESCE(T2."Comments", T5."Comments") AS "CommentsFactura",
    COALESCE(T2."ObjType", T5."ObjType") AS "ObjType"
FROM ORCT T0
LEFT JOIN RCT2 T1 ON T0."DocEntry" = T1."DocNum"
LEFT JOIN OPCH T2 ON T1."DocEntry" = T2."DocEntry" AND T1."InvType" = 18
LEFT JOIN PCH1 T4 ON T2."DocEntry" = T4."DocEntry"
LEFT JOIN OINV T5 ON T1."DocEntry" = T5."DocEntry" AND T1."InvType" = 13
LEFT JOIN INV1 T6 ON T5."DocEntry" = T6."DocEntry"
LEFT JOIN RCT4 T3 ON T0."DocEntry" = T3."DocNum"
WHERE T0."CashAcct" IN ('12345678', '87654321', '12345678', '87654321', '12345678', '11223344', '44332211', '11001100', '00110011', '00112233')

UNION ALL

-- =========================================================================
-- 3. DEPOSITOS
-- =========================================================================


SELECT
    'DEPOSITO' AS "ModuloOrigen",
    T0."DeposNum" AS "Pago",
    T0."DeposDate" AS "DocDate",
    NULL AS "CardCode",
    NULL AS "CardName",
    T0."BanckAcct" AS "CashAcct",
    CASE 
        WHEN T0."DeposType" = 'C' THEN 'DEPOSITO CHEQUES'
        WHEN T0."DeposType" = 'K' THEN 'DEPOSITO EFECTIVO'
        WHEN T0."DeposType" = 'V' THEN 'DEPOSITO TARJETA'
        ELSE 'DEPOSITO'
    END AS "TipoDetalle",
    0 AS "Linea",
    NULL AS "InvType",
    NULL AS "DocumentoOrigen",
    NULL AS "FechaOrigen",
    NULL AS "TotalOrigen",
    NULL AS "OcrCode",
    T0."LocTotal" AS "GTotal",
    T0."Memo" AS "CommentsPago",
    NULL AS "NumAtCardFactura",
    NULL AS "CommentsFactura",
    NULL AS "ObjType"
FROM ODPS T0
WHERE T0."BanckAcct" IN ('12345678', '87654321', '12345678', '87654321', '12345678', '11223344', '44332211', '11001100', '00110011', '00112233')

ORDER BY "DocDate", "Pago", "ModuloOrigen", "Linea";
