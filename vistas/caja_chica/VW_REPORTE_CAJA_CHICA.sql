CREATE VIEW
    "VW_REPORTE_CAJA_CHICA" AS
SELECT
    T0."DocNum" AS "Pago",
    T0."DocDate",
    T0."CardCode",
    T0."CardName",
    T0."CashAcct",
    'FACTURA' AS "TipoDetalle",
    T1."DocLine" AS "Linea",
    T1."InvType",
    T1."DocEntry" AS "DocEntryOrigen",
    T4."Dscription" AS "Descripcion",
    T1."SumApplied",
    T2."DocNum" AS "DocumentoOrigen",
    T2."DocDate" AS "FechaOrigen",
    T2."DocTotal" AS "TotalOrigen",
    T4."OcrCode" AS "OcrCode",
    T4."GTotal" AS "GTotal",
    T0."Comments" AS "CommentsPago",
    T2."NumAtCard" AS "NumAtCardFactura",
    T2."Comments" AS "CommentsFactura",
    T2."ObjType" AS "ObjType"
FROM
    OVPM T0
    INNER JOIN VPM2 T1 ON T0."DocEntry" = T1."DocNum"
    LEFT JOIN OPCH T2 ON T1."DocEntry" = T2."DocEntry"
    AND T1."InvType" = 18
    LEFT JOIN PCH1 T4 ON T2."DocEntry" = T4."DocEntry"
WHERE
    T0."CashAcct" IN ('12345678', '87654321') /*Reemplazar con las cuentas de caja chica correspondientes*/
UNION ALL
SELECT
    T0."DocNum" AS "Pago",
    T0."DocDate",
    T0."CardCode",
    T0."CardName",
    T0."CashAcct",
    'CUENTA' AS "TipoDetalle",
    T3."LineId" AS "Linea",
    NULL AS "InvType",
    T3."AcctCode" AS "DocEntryOrigen",
    T3."Descrip" AS "Descripcion",
    T3."SumApplied",
    T3."AcctCode" AS "DocumentoOrigen",
    NULL AS "FechaOrigen",
    NULL AS "TotalOrigen",
    NULL AS "OcrCode",
    T3."SumApplied" AS "GTotal",
    NULL AS "CommentsPago",
    NULL AS "NumAtCardFactura",
    NULL AS "CommentsFactura",
    NULL AS "ObjType"
FROM
    OVPM T0
    INNER JOIN VPM4 T3 ON T0."DocEntry" = T3."DocNum"
WHERE
    T0."CashAcct" IN ('12345678', '87654321') /*Reemplazar con las cuentas de caja chica correspondientes*/
ORDER BY
    "Pago",
    "TipoDetalle",
    "Linea"