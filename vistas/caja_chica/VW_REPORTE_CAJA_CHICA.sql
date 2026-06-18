CREATE VIEW
    "VW_REPORTE_CAJA_CHICA" AS
SELECT
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
-- Unión con detalle de Facturas (Proveedores o Clientes)
LEFT JOIN VPM2 T1 ON T0."DocEntry" = T1."DocNum"
LEFT JOIN OPCH T2 ON T1."DocEntry" = T2."DocEntry" AND T1."InvType" = 18
LEFT JOIN PCH1 T4 ON T2."DocEntry" = T4."DocEntry"
LEFT JOIN OINV T5 ON T1."DocEntry" = T5."DocEntry" AND T1."InvType" = 13
LEFT JOIN INV1 T6 ON T5."DocEntry" = T6."DocEntry"
-- Unión con detalle de Cuentas
LEFT JOIN VPM4 T3 ON T0."DocEntry" = T3."DocNum"
WHERE T0."CashAcct" IN ('12345678', '87654321') -- Reemplaza con las cuentas de caja chica correspondientes
ORDER BY "Pago", "Linea";