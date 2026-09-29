import React, { useEffect, useRef } from "react";
import { useDispatch, useSelector } from "react-redux";
import { useParams } from "react-router-dom";
import {
  FaLinkedin,
  FaMedal,
  FaStar,
  FaDownload,
  FaUser,
  FaIdCard,
  FaChartLine,
  FaQuestionCircle,
  FaClock,
  FaGraduationCap,
  FaCheckCircle,
  FaTimesCircle,
} from "react-icons/fa";
import { MdVerified, MdOutlineEmojiEvents } from "react-icons/md";
import { GiLaurelCrown, GiQuillInk } from "react-icons/gi";
import {
  PageLayout,
  PageHeader,
  PageHeaderLeft,
  PageHeaderRight,
  PageTitle,
  PageSubtitle,
  PageBody,
} from "../../../common/layout";
import Loader from "../../../common/Loader";
import Error from "../../../common/Error";
import { getCertificateById } from "../../../../../redux/slice/reportSlice";
import Breadcrumb from "../../../common/layout/Breadcrumb";
import { toJpeg } from "html-to-image";
import jsPDF from "jspdf";
import { useTranslation } from "react-i18next";

// Backend CORS headers nahi bhejta, isliye images ko ek CORS-friendly
// proxy ke through load karte hain sirf html2canvas capture ke liye.
const toCorsProxyUrl = (url) => {
  if (!url) return url;
  try {
    const clean = url.replace(/^https?:\/\//, "");
    return `https://images.weserv.nl/?url=${encodeURIComponent(clean)}`;
  } catch {
    return url;
  }
};

// Reusable icon + text row.
// html2canvas me SVG icon aur text ka baseline alag render hota hai, isliye:
//  - poori row ki ek fixed height hai (size + 4)
//  - icon ka wrapper bhi wahi height leta hai aur flex se center hota hai
//  - icon `display: block` hai (baseline gap khatam)
//  - text ka line-height = row height
const IconLabel = ({
  icon: Icon,
  children,
  className = "",
  iconClassName = "",
  size = 12,
}) => {
  const rowHeight = size + 4;
  return (
    <span
      className={className}
      style={{
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        gap: 6,
        height: rowHeight,
        lineHeight: `${rowHeight}px`,
      }}
    >
      <span
        className={iconClassName}
        style={{
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          width: size,
          height: rowHeight,
          flexShrink: 0,
        }}
      >
        <Icon size={size} style={{ display: "block" }} />
      </span>
      <span
        style={{
          display: "block",
          lineHeight: `${rowHeight}px`,
          height: rowHeight,
        }}
      >
        {children}
      </span>
    </span>
  );
};

const Certificate = () => {
  const { id } = useParams();
  const dispatch = useDispatch();
  const { t } = useTranslation();
  const certificateRef = useRef();
  const {
    currentCertificate: certificate,
    loadingCertificate,
    isError,
    message,
  } = useSelector((state) => state.report);

  useEffect(() => {
    if (id) {
      dispatch(getCertificateById(id));
    }
  }, [dispatch, id]);

  const handleDownload = async () => {
    const element = certificateRef.current;
    if (!element) return;

    try {
      await document.fonts.ready;

      // html-to-image browser ke native rendering se capture karta hai,
      // isliye PDF live preview jaisi hi aati hai (html2canvas ka alag
      // layout engine icon/text ko shift kar deta tha).
      const imgData = await toJpeg(element, {
        quality: 1,
        pixelRatio: 2,
        backgroundColor: "#ffffff",
      });

      const elWidth = element.offsetWidth;
      const elHeight = element.offsetHeight;

      const pdf = new jsPDF({
        orientation: "landscape",
        unit: "mm",
        format: "a4",
      });
      const pdfWidth = pdf.internal.pageSize.getWidth();
      const pdfHeight = pdf.internal.pageSize.getHeight();
      const ratio = Math.min(pdfWidth / elWidth, pdfHeight / elHeight);
      const imgWidth = elWidth * ratio;
      const imgHeight = elHeight * ratio;
      const x = (pdfWidth - imgWidth) / 2;
      const y = (pdfHeight - imgHeight) / 2;
      pdf.addImage(imgData, "JPEG", x, y, imgWidth, imgHeight);
      pdf.save(`${certificate?.data?.certificate_id || "certificate"}.pdf`);
    } catch (error) {
      console.error("Download failed:", error);
    }
  };

  if (loadingCertificate) return <Loader />;
  if (isError) return <Error message={message} />;

  const { data } = certificate || {};
  const {
    design,
    meta,
    share_links,
    certificate_id,
    issued_at,
    content,
    context_details,
  } = data || {};

  const user = meta?.user || {};
  const result = meta?.result || {};
  const questions = meta?.questions || {};
  const marks = meta?.marks || {};
  const time = meta?.time || {};
  const attempt = meta?.attempt || {};
  const context = meta?.context || {};

  // "topic" => quiz certificate (single topic), "module" => exam certificate (whole module)
  const isModuleCert = context?.type === "module";
  const certTypeLabel = isModuleCert
    ? t("certificate.types.moduleExam")
    : t("certificate.types.quiz");
  const certTypeShort = isModuleCert
    ? t("certificate.types.exam")
    : t("certificate.types.quiz");

  const formatDate = (dateStr) => {
    if (!dateStr) return "-";
    return new Date(dateStr).toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  return (
    <PageLayout>
      <Breadcrumb
        items={[
          {
            label: t("certificate.breadcrumb.certification"),
            path: `/certification`,
          },
          { label: t("certificate.breadcrumb.view") },
        ]}
      />

      {/* Congratulatory Banner */}
      <div className="mb-6 bg-gradient-to-r from-blue-50 to-teal-50 rounded-xl p-5 border border-blue-200 shadow-sm">
        <div className="flex items-center gap-4">
          <div className="text-3xl text-blue-600">
            <MdOutlineEmojiEvents />
          </div>
          <div>
            <div className="flex items-center gap-2 flex-wrap">
              <h1 className="text-xl font-bold text-gray-800">
                {t("certificate.banner.congratulations")}{" "}
                <span className="text-blue-700">
                  {user?.name || t("certificate.banner.recipient")}
                </span>
                !
              </h1>
              <span className="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-blue-700 text-white rounded-full">
                {certTypeLabel}
              </span>
            </div>
            <p className="text-sm text-gray-600">
              {isModuleCert
                ? t("certificate.banner.examPassed")
                : t("certificate.banner.quizMastered")}{" "}
              <span className="font-semibold text-teal-700">
                {context?.title || t("certificate.banner.topic")}
              </span>
              .
            </p>
          </div>
        </div>
      </div>

      {/* Action Buttons Row - Only LinkedIn & Download */}
      <div className="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div className="flex flex-wrap items-center gap-3">
          {share_links?.linkedin && (
            <button
              onClick={() => window.open(share_links.linkedin, "_blank")}
              className="flex items-center gap-2 px-5 py-2 text-sm font-medium text-blue-700 border border-blue-700 rounded-md cursor-pointer hover:bg-blue-50 transition-colors"
            >
              <FaLinkedin size={15} className="text-blue-600" />{" "}
              {t("certificate.buttons.shareLinkedin")}
            </button>
          )}
          <button
            onClick={handleDownload}
            className="flex items-center gap-2 px-5 py-2 text-sm font-medium text-white bg-blue-700 rounded-md cursor-pointer hover:bg-blue-800 transition-colors"
          >
            <FaDownload size={14} /> {t("certificate.buttons.downloadPdf")}
          </button>
        </div>
        <div className="text-xs text-gray-500">
          {t("certificate.buttons.certificateId")}{" "}
          <span className="font-mono text-blue-700 font-medium">
            {certificate_id}
          </span>
        </div>
      </div>

      {/* Two Column Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-8 gap-6">
        {/* Left Column: Certificate */}
        <div className="lg:col-span-5">
          <div className="flex justify-center items-center bg-gradient-to-br from-blue-50 via-white to-teal-50 rounded-xl p-3 shadow-lg border border-blue-100">
            <div
              ref={certificateRef}
              className="w-full max-w-3xl bg-white shadow-2xl relative overflow-hidden rounded-lg"
            >
              {/* Premium Border */}
              <div className="absolute inset-3 border-2 border-blue-700 pointer-events-none"></div>
              <div className="absolute inset-5 border border-blue-500 pointer-events-none"></div>
              <div className="absolute inset-6 border border-teal-400 pointer-events-none"></div>

              {/* Corner Decorations */}
              <div className="absolute top-5 left-5 w-12 h-12 border-t-4 border-l-4 border-blue-700"></div>
              <div className="absolute top-5 right-5 w-12 h-12 border-t-4 border-r-4 border-blue-700"></div>
              <div className="absolute bottom-5 left-5 w-12 h-12 border-b-4 border-l-4 border-blue-700"></div>
              <div className="absolute bottom-5 right-5 w-12 h-12 border-b-4 border-r-4 border-blue-700"></div>

              <div className="absolute top-10 left-10 text-blue-600 opacity-50">
                <GiLaurelCrown size={20} />
              </div>
              <div className="absolute top-10 right-10 text-blue-600 opacity-50">
                <GiLaurelCrown size={20} />
              </div>
              <div className="absolute bottom-10 left-10 text-blue-600 opacity-50">
                <GiQuillInk size={20} />
              </div>
              <div className="absolute bottom-10 right-10 text-blue-600 opacity-50">
                <GiQuillInk size={20} />
              </div>

              {/* Main Content */}
              <div className="p-10 print:p-8 relative z-10">
                {/* Company Logo */}
                {design?.company_logo && (
                  <div className="flex justify-center mb-3">
                    <img
                      src={toCorsProxyUrl(design.company_logo)}
                      alt={design.company_name}
                      className="h-14 object-contain"
                      crossOrigin="anonymous"
                    />
                  </div>
                )}

                {design?.company_name && (
                  <div className="text-center mb-0.5">
                    <h1 className="text-2xl font-serif font-bold text-blue-800 tracking-wide">
                      {design.company_name}
                    </h1>
                  </div>
                )}

                {design?.tagline && (
                  <div className="text-center mb-2">
                    <p className="text-xs text-gray-500 italic tracking-wide uppercase">
                      {design.tagline}
                    </p>
                  </div>
                )}

                <div className="flex justify-center items-center gap-3 my-3">
                  <div className="h-px w-16 bg-blue-500"></div>
                  <FaStar className="text-blue-500 text-xs" />
                  <div className="h-px w-16 bg-blue-500"></div>
                </div>

                {design?.heading && (
                  <div className="text-center mb-4">
                    <h2 className="text-xl font-serif text-blue-700 uppercase tracking-wider">
                      {design.heading}
                    </h2>
                    {/* Medal row: teeno elements ko same fixed 16px height
                        di hai taaki PDF capture me icons upar na chadhein */}
                    <div
                      className="flex justify-center items-center mt-1"
                      style={{ gap: 8, height: 16 }}
                    >
                      <span
                        className="text-blue-600"
                        style={{
                          display: "flex",
                          alignItems: "center",
                          height: 16,
                        }}
                      >
                        <FaMedal size={12} style={{ display: "block" }} />
                      </span>
                      <span
                        className="text-xs text-gray-500"
                        style={{
                          display: "block",
                          lineHeight: "16px",
                          height: 16,
                        }}
                      >
                        {isModuleCert
                          ? t("certificate.certificate.examPassedText")
                          : t("certificate.certificate.quizCompletedText")}
                      </span>
                      <span
                        className="text-blue-600"
                        style={{
                          display: "flex",
                          alignItems: "center",
                          height: 16,
                        }}
                      >
                        <FaMedal size={12} style={{ display: "block" }} />
                      </span>
                    </div>
                  </div>
                )}

                <div className="text-center my-4">
                  <p className="text-gray-600 text-xs">
                    {t("certificate.certificate.presentedTo")}
                  </p>
                  <div className="my-3">
                    <div className="inline-block border-b-4 border-blue-600 px-10 pb-3">
                      <p className="text-3xl font-serif font-bold text-blue-900 tracking-wide leading-none">
                        {user?.name ||
                          t("certificate.certificate.recipientName")}
                      </p>
                    </div>
                  </div>
                  <p className="text-sm text-gray-600 mb-1">
                    {isModuleCert
                      ? t("certificate.certificate.forModule")
                      : t("certificate.certificate.forTopic")}
                  </p>
                  <p className="text-base font-serif font-semibold text-teal-700 mb-2">
                    {context?.title || "-"}
                  </p>
                  {/* ID row */}
                  <div
                    className="flex justify-center items-center gap-4 text-xs text-gray-500"
                    style={{ height: 18, marginTop: 4 }}
                  >
                    <IconLabel
                      icon={FaIdCard}
                      iconClassName="text-blue-600"
                      size={12}
                    >
                      {t("certificate.certificate.id")}:{" "}
                      {user?.employee_id || "-"}
                    </IconLabel>
                  </div>
                </div>

                {content && (
                  <div className="my-4 text-sm">
                    <div
                      dangerouslySetInnerHTML={{ __html: content }}
                      style={{ textAlign: "center" }}
                    />
                  </div>
                )}

                {/* Status strip */}
                <div className="my-4 border-t border-b border-blue-200 py-3">
                  <div className="grid grid-cols-3 items-center text-center">
                    {/* Left */}
                    <div className="flex items-center justify-center h-full">
                      <div className="w-16 h-16 rounded-full border-2 border-blue-200 bg-blue-50 flex items-center justify-center">
                        <GiLaurelCrown className="text-4xl text-blue-600" />
                      </div>
                    </div>

                    {/* Center */}
                    <div className="border-x border-blue-200 px-4">
                      <div className="text-2xl font-serif font-bold text-blue-800 capitalize">
                        {result?.status === "passed"
                          ? t("certificate.metrics.passed")
                          : t("certificate.metrics.completed")}
                      </div>
                      <div className="text-[9px] text-gray-500 uppercase tracking-wider">
                        {t("certificate.metrics.status")}
                      </div>
                    </div>

                    {/* Right */}
                    <div className="flex items-center justify-center h-full">
                      <div className="w-16 h-16 rounded-full border-2 border-blue-200 bg-blue-50 flex items-center justify-center">
                        <GiLaurelCrown className="text-4xl text-blue-600" />
                      </div>
                    </div>
                  </div>
                </div>

                {/* Signature & Date */}
                <div className="grid grid-cols-2 gap-6 mt-4 pt-3">
                  <div className="text-center">
                    {design?.signer_signature && (
                      <img
                        src={toCorsProxyUrl(design.signer_signature)}
                        alt={t("certificate.certificate.signature")}
                        className="h-10 mx-auto mb-1.5 object-contain"
                        crossOrigin="anonymous"
                      />
                    )}
                    <div className="border-b-2 border-blue-600 w-32 mx-auto"></div>
                    <p className="text-sm font-serif font-semibold text-gray-800 mt-2">
                      {design?.signer_name}
                    </p>
                    <p className="text-xs text-gray-500">
                      {design?.signer_designation ||
                        t("certificate.certificate.authorizedSignatory")}
                    </p>
                  </div>
                  <div className="text-center flex flex-col justify-end">
                    <div className=" w-32 mx-auto"></div>
                    <p className="text-sm font-serif font-semibold text-gray-800 mt-2">
                      {issued_at
                        ? new Date(issued_at).toLocaleDateString("en-US", {
                            year: "numeric",
                            month: "long",
                            day: "numeric",
                          })
                        : new Date().toLocaleDateString("en-US", {
                            year: "numeric",
                            month: "long",
                            day: "numeric",
                          })}
                    </p>
                    <p className="text-xs text-gray-500 mt-0.5">
                      {t("certificate.certificate.dateOfIssue")}
                    </p>
                  </div>
                </div>

                {/* Footer */}
                <div className="text-center mt-6 pt-3 border-t border-gray-200">
                  <p className="text-[10px] font-mono text-blue-700">
                    {certificate_id}
                  </p>
                  {design?.footer_text && (
                    <p className="text-[9px] text-gray-500 mt-0.5">
                      {design.footer_text}
                    </p>
                  )}
                  <div
                    className="flex justify-center items-center text-[9px] text-gray-400"
                    style={{ gap: 4, height: 14, marginTop: 6 }}
                  >
                    <span
                      style={{
                        display: "flex",
                        alignItems: "center",
                        height: 14,
                      }}
                    >
                      <MdVerified size={11} style={{ display: "block" }} />
                    </span>
                    <span style={{ display: "block", lineHeight: "14px" }}>
                      {t("certificate.certificate.digitallyVerified")}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Right Column: Compact Data Sections */}
        <div className="lg:col-span-3 space-y-3">
          {/* Recipient - compact */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div className="px-3 py-2 bg-blue-50 border-b border-blue-200">
              <IconLabel
                icon={FaUser}
                iconClassName="text-blue-600"
                size={11}
                className="text-[10px] font-semibold text-blue-800 uppercase tracking-wider"
              >
                {t("certificate.details.recipient")}
              </IconLabel>
            </div>
            <div className="px-3 py-2">
              <div className="grid grid-cols-2 gap-1 text-xs">
                <div>
                  <span className="text-gray-400 text-[9px]">
                    {t("certificate.details.name")}
                  </span>
                  <p className="font-medium text-gray-800 text-xs">
                    {user?.name || "-"}
                  </p>
                </div>
                <div>
                  <span className="text-gray-400 text-[9px]">
                    {t("certificate.details.employeeId")}
                  </span>
                  <p className="font-medium text-gray-800 text-xs">
                    {user?.employee_id || "-"}
                  </p>
                </div>
                <div className="col-span-2">
                  <span className="text-gray-400 text-[9px]">
                    {t("certificate.details.email")}
                  </span>
                  <p className="font-medium text-gray-800 text-xs truncate">
                    {user?.email || "-"}
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Result + Questions combined in one row */}
          <div className="grid grid-cols-2 gap-3">
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
              <div className="px-3 py-2 bg-blue-50 border-b border-blue-200">
                <IconLabel
                  icon={FaChartLine}
                  iconClassName="text-blue-600"
                  size={11}
                  className="text-[10px] font-semibold text-blue-800 uppercase tracking-wider"
                >
                  {t("certificate.details.result")}
                </IconLabel>
              </div>
              <div className="px-3 py-2">
                <div className="space-y-1 text-xs">
                  <div className="flex justify-between">
                    <span className="text-gray-400 text-[9px]">
                      {t("certificate.details.score")}
                    </span>
                    <span className="font-medium text-gray-800">
                      {result?.score || 0} / {marks?.total_marks || 0}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-400 text-[9px]">
                      {t("certificate.details.percentage")}
                    </span>
                    <span className="font-medium text-gray-800">
                      {result?.percentage || 0}%
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-400 text-[9px]">
                      {t("certificate.details.passing")}
                    </span>
                    <span className="font-medium text-gray-800">
                      {result?.passing_score || 0}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-400 text-[9px]">
                      {t("certificate.details.status")}
                    </span>
                    <span
                      className={`font-medium capitalize text-xs ${result?.status === "passed" ? "text-green-600" : "text-orange-500"}`}
                    >
                      {result?.status === "passed" ? (
                        <IconLabel icon={FaCheckCircle} size={10}>
                          {t("certificate.details.passed")}
                        </IconLabel>
                      ) : (
                        <IconLabel icon={FaTimesCircle} size={10}>
                          {result?.status || "-"}
                        </IconLabel>
                      )}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
              <div className="px-3 py-2 bg-blue-50 border-b border-blue-200">
                <IconLabel
                  icon={FaQuestionCircle}
                  iconClassName="text-blue-600"
                  size={11}
                  className="text-[10px] font-semibold text-blue-800 uppercase tracking-wider"
                >
                  {t("certificate.details.questions")}
                </IconLabel>
              </div>
              <div className="px-3 py-2">
                <div className="space-y-1 text-xs">
                  <div className="flex justify-between">
                    <span className="text-gray-400 text-[9px]">
                      {t("certificate.details.total")}
                    </span>
                    <span className="font-medium text-gray-800">
                      {questions?.total || 0}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-400 text-[9px]">
                      {t("certificate.details.attempted")}
                    </span>
                    <span className="font-medium text-gray-800">
                      {questions?.attempted || 0}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-400 text-[9px]">
                      {t("certificate.details.correct")}
                    </span>
                    <span className="font-medium text-green-600">
                      {questions?.correct || 0}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-400 text-[9px]">
                      {t("certificate.details.incorrect")}
                    </span>
                    <span className="font-medium text-red-500">
                      {questions?.incorrect || 0}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-400 text-[9px]">
                      {t("certificate.details.skipped")}
                    </span>
                    <span className="font-medium text-gray-800">
                      {questions?.skipped || 0}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Time & Attempt - compact */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div className="px-3 py-2 bg-blue-50 border-b border-blue-200">
              <IconLabel
                icon={FaClock}
                iconClassName="text-blue-600"
                size={11}
                className="text-[10px] font-semibold text-blue-800 uppercase tracking-wider"
              >
                {t("certificate.details.timeAndAttempt")}
              </IconLabel>
            </div>
            <div className="px-3 py-2">
              <div className="grid grid-cols-3 gap-1 text-xs">
                <div>
                  <span className="text-gray-400 text-[9px]">
                    {t("certificate.details.started")}
                  </span>
                  <p className="font-medium text-gray-800 text-[10px]">
                    {formatDate(time?.started_at)}
                  </p>
                </div>
                <div>
                  <span className="text-gray-400 text-[9px]">
                    {t("certificate.details.submitted")}
                  </span>
                  <p className="font-medium text-gray-800 text-[10px]">
                    {formatDate(time?.submitted_at)}
                  </p>
                </div>
                <div>
                  <span className="text-gray-400 text-[9px]">
                    {t("certificate.details.timeTaken")}
                  </span>
                  <p className="font-medium text-gray-800 text-[10px]">
                    {Math.floor((time?.time_taken_seconds || 0) / 60)}m{" "}
                    {Math.floor((time?.time_taken_seconds || 0) % 60)}s
                  </p>
                </div>
                <div className="col-span-3">
                  <span className="text-gray-400 text-[9px]">
                    {t("certificate.details.attemptNumber")}
                  </span>
                  <p className="font-medium text-gray-800 text-[10px]">
                    {attempt?.attempt_id || "-"}
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Course Context - compact */}
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div className="px-3 py-2 bg-blue-50 border-b border-blue-200 flex items-center gap-1.5">
              <IconLabel
                icon={FaGraduationCap}
                iconClassName="text-blue-600"
                size={11}
                className="text-[10px] font-semibold text-blue-800 uppercase tracking-wider flex-1"
              >
                {t("certificate.details.courseContext")}
              </IconLabel>
              <span className="px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wider bg-blue-700 text-white rounded">
                {certTypeShort}
              </span>
            </div>
            <div className="px-3 py-2">
              <div className="grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs">
                <div className="flex justify-between col-span-2">
                  <span className="text-gray-400 text-[9px]">
                    {t("certificate.details.program")}
                  </span>
                  <span className="font-medium text-gray-800 text-[10px]">
                    {context_details?.program?.title || "-"}
                  </span>
                </div>
                <div className="flex justify-between col-span-2">
                  <span className="text-gray-400 text-[9px]">
                    {t("certificate.details.level")}
                  </span>
                  <span className="font-medium text-gray-800 text-[10px]">
                    {context_details?.level?.title || "-"}
                  </span>
                </div>
                <div className="flex justify-between col-span-2">
                  <span className="text-gray-400 text-[9px]">
                    {t("certificate.details.module")}
                  </span>
                  <span
                    className={`font-medium text-[10px] truncate max-w-[140px] ${isModuleCert ? "text-blue-700" : "text-gray-800"}`}
                  >
                    {context_details?.module?.title || "-"}
                  </span>
                </div>

                {/* Chapter & Topic sirf topic-level (quiz) certificate ke liye.
                    Module-level (exam) certificate saare chapters/topics cover karta hai. */}
                {!isModuleCert && (
                  <>
                    <div className="flex justify-between col-span-2">
                      <span className="text-gray-400 text-[9px]">
                        {t("certificate.details.chapter")}
                      </span>
                      <span className="font-medium text-gray-800 text-[10px] truncate max-w-[140px]">
                        {context_details?.chapter?.title || "-"}
                      </span>
                    </div>
                    <div className="flex justify-between col-span-2">
                      <span className="text-gray-400 text-[9px]">
                        {t("certificate.details.topic")}
                      </span>
                      <span className="font-medium text-teal-700 text-[10px] truncate max-w-[140px]">
                        {context_details?.topic?.title || "-"}
                      </span>
                    </div>
                  </>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </PageLayout>
  );
};

export default Certificate;